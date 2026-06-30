<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
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
