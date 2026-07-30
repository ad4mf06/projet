<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EpoqueHistorique;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
use App\Models\RegionAdministrative;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un musée publié avec son arborescence complète et retourne les entités clés.
 *
 * @return array{ProjetRecherche, MuseeMeta, MuseePublication}
 */
function creerMuseePublie(string $slugBase = 'test-musee'): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours public test',
        'code' => '330-PB',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['code' => 'H2025', 'cours_id' => $cours->id]);

    $groupe = Groupe::create([
        'numero' => 1,
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée public',
        'type' => 'musee',
        'accessible' => true,
    ]);

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    // L'observer crée MuseeMeta — on s'assure qu'il a un slug connu
    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first()
        ?? MuseeMeta::create([
            'projet_recherche_id' => $projet->id,
            'slug' => $slugBase,
        ]);

    $meta->update(['slug' => $slugBase, 'entete_titre' => 'Musée de test']);

    $publication = MuseePublication::create([
        'projet_recherche_id' => $projet->id,
        'est_publie' => true,
        'publie_le' => now(),
        'publie_par' => $enseignant->id,
    ]);

    return [$projet, $meta, $publication];
}

// ─── Accès à la page publique ─────────────────────────────────────────────────

test('la page publique est accessible sans authentification si le musée est publié', function () {
    [$projet, $meta] = creerMuseePublie('musee-publie-ok');

    $this->get("/musee/{$meta->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Show')
            ->has('meta')
            ->has('sections')
            ->has('images')
            ->has('membres')
            ->has('cssVars')
            ->has('nbVues')
        );
});

test('un musée non publié retourne 404', function () {
    [$projet, $meta, $publication] = creerMuseePublie('musee-non-publie');

    // Dépublier le musée
    $publication->update(['est_publie' => false]);

    $this->get("/musee/{$meta->slug}")
        ->assertNotFound();
});

test('un slug inexistant retourne 404', function () {
    $this->get('/musee/slug-inexistant-9999')
        ->assertNotFound();
});

// ─── Compteur de vues ─────────────────────────────────────────────────────────

test('la première visite enregistre une vue', function () {
    [$projet, $meta] = creerMuseePublie('musee-vue-1');

    expect($projet->museeVues()->count())->toBe(0);

    $this->get("/musee/{$meta->slug}");

    expect($projet->museeVues()->count())->toBe(1);
});

test('deux visites avec la même IP dans les 24h ne comptent qu\'une seule vue', function () {
    [$projet, $meta] = creerMuseePublie('musee-vue-dedup');

    $this->get("/musee/{$meta->slug}");
    $this->get("/musee/{$meta->slug}");

    // La même IP de test ne doit compter qu'une fois
    expect($projet->museeVues()->count())->toBe(1);
});

// ─── Sections et blocs ─────────────────────────────────────────────────────────

test('les sections avec leurs blocs sont transmises à la vue publique', function () {
    [$projet, $meta] = creerMuseePublie('musee-avec-blocs');

    $typeProjet = $projet->typeProjet;

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Ma section',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'texte',
        'contenu' => ['html' => '<p>Bonjour</p>'],
        'ordre' => 1,
    ]);

    $this->get("/musee/{$meta->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Show')
            ->has('sections', 1)
            ->where('sections.0.label', 'Ma section')
            ->has('sections.0.blocs', 1)
            ->where('sections.0.blocs.0.type', 'texte')
        );
});

// ─── Page d'accueil (/musee) ──────────────────────────────────────────────────

test('la page accueil du musée est accessible sans authentification', function () {
    $this->get('/musee')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Accueil')
            ->has('miseEnAvant')
            ->has('total')
        );
});

test('la page accueil expose les musées publiés dans miseEnAvant', function () {
    [, $meta] = creerMuseePublie('musee-accueil-1');

    $this->get('/musee')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Accueil')
            ->where('total', 1)
            ->has('miseEnAvant', 1)
            ->where('miseEnAvant.0.slug', $meta->slug)
        );
});

test('la page accueil n\'expose pas les musées non publiés', function () {
    [$projet, $meta, $publication] = creerMuseePublie('musee-accueil-non-publie');
    $publication->update(['est_publie' => false]);

    $this->get('/musee')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('total', 0)
            ->has('miseEnAvant', 0)
        );
});

// ─── Page explorateur (/musee/explorer) ───────────────────────────────────────

test('la page explorer est accessible sans authentification', function () {
    $this->get('/musee/explorer')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Explorer')
            ->has('musees')
            ->has('filtres')
            ->has('options')
            ->has('options.epoques')
            ->has('options.regions')
            ->has('options.thematiques')
        );
});

test('la page explorer retourne les musées publiés paginés', function () {
    creerMuseePublie('musee-exp-1');
    creerMuseePublie('musee-exp-2');

    $this->get('/musee/explorer')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Musee/Public/Explorer')
            ->where('musees.total', 2)
        );
});

test('le filtre par époque sur explorer restreint les résultats', function () {
    $epoque = EpoqueHistorique::create(['nom' => 'Nouvelle-France', 'ordre' => 1]);

    [, $metaAvec] = creerMuseePublie('musee-epoque-oui');
    $metaAvec->update(['epoque_historique_id' => $epoque->id]);

    creerMuseePublie('musee-epoque-non'); // sans époque

    $this->get("/musee/explorer?epoque_ids[]={$epoque->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('musees.total', 1)
            ->where('musees.data.0.slug', $metaAvec->slug)
        );
});

test('le filtre par région administrative sur explorer restreint les résultats', function () {
    $region = RegionAdministrative::create(['nom' => 'Bas-Saint-Laurent', 'code_region' => '01', 'ordre' => 1]);

    [, $metaAvec] = creerMuseePublie('musee-region-oui');
    $metaAvec->update(['region_administrative_id' => $region->id]);

    creerMuseePublie('musee-region-non'); // sans région

    $this->get("/musee/explorer?region_ids[]={$region->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('musees.total', 1)
            ->where('musees.data.0.slug', $metaAvec->slug)
        );
});

// ─── Page contribuer (/musee/contribuer) ─────────────────────────────────────

test('la page contribuer est accessible sans authentification', function () {
    $this->get('/musee/contribuer')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Musee/Public/Contribuer'));
});

// ─── Page d'accueil racine (/) ────────────────────────────────────────────────

test('la page racine rend le composant Home sans redirection', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Home'));
});

test('la page racine est accessible même pour un utilisateur connecté', function () {
    $user = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Home'));
});
