<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetConclusion;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée les entités nécessaires à un scénario de test :
 * un enseignant, une classe, un groupe, et des étudiants membres.
 *
 * @return array{enseignant: User, classe: Classe, groupe: Groupe, etudiant1: User, etudiant2: User}
 */
function creerScenario(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'heures_par_semaine' => 3,
        'code' => '330-2E1',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant1 = User::factory()->create(['role' => 'etudiant']);
    $etudiant2 = User::factory()->create(['role' => 'etudiant']);

    $classe->etudiants()->attach([$etudiant1->id, $etudiant2->id]);

    $groupe = Groupe::create([
        'nom' => 'Équipe A',
        'classe_id' => $classe->id,
        'created_by' => $etudiant1->id,
    ]);

    $groupe->membres()->attach([$etudiant1->id, $etudiant2->id]);

    return compact('enseignant', 'classe', 'groupe', 'etudiant1', 'etudiant2');
}

// ─── Accès à l'index ──────────────────────────────────────────────────────────

test("un membre du groupe peut accéder à l'index des projets", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertOk();
});

test("l'enseignant de la classe peut accéder à l'index des projets", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertOk();
});

test('un invité non authentifié est redirigé vers login', function () {
    ['classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertRedirect(route('login'));
});

test("un étudiant extérieur au groupe ne peut pas accéder à l'index", function () {
    ['classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etranger->id);

    $this->actingAs($etranger)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertForbidden();
});

// ─── Accès à la page show (projet partagé) ────────────────────────────────────

test('un membre peut accéder à la page du projet partagé', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk();
});

test("l'enseignant peut consulter le projet partagé en lecture seule", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk();
});

// ─── Mise à jour du contenu partagé ───────────────────────────────────────────

test('un membre peut enregistrer le contenu partagé du projet', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets", [
            'titre_projet' => 'L\'agriculture québécoise',
            'introduction_amener' => '<p>Contexte général…</p>',
            'dev_1_titre' => 'Les origines',
            'dev_1_contenu' => '<p>Contenu du paragraphe 1…</p>',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'completion']);

    $this->assertDatabaseHas('projets_recherche', [
        'groupe_id' => $groupe->id,
        'titre_projet' => 'L\'agriculture québécoise',
    ]);
});

test('un deuxième PUT met à jour sans créer de doublon', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets";

    $this->actingAs($etudiant)->putJson($url, ['titre_projet' => 'Titre V1']);
    $this->actingAs($etudiant)->putJson($url, ['titre_projet' => 'Titre V2']);

    // Un seul projet par groupe
    expect(ProjetRecherche::where('groupe_id', $groupe->id)->count())->toBe(1);
    $this->assertDatabaseHas('projets_recherche', ['titre_projet' => 'Titre V2']);
});

test("l'enseignant ne peut pas modifier le contenu partagé", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets", [
            'titre_projet' => 'Modification non autorisée',
        ])
        ->assertForbidden();
});

// ─── Conclusion individuelle ───────────────────────────────────────────────────

test('un membre peut enregistrer sa conclusion individuelle', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/conclusion", [
            'contenu' => '<p>Ma conclusion personnelle…</p>',
        ])
        ->assertOk()
        ->assertJson(['message' => 'saved']);

    $projet = ProjetRecherche::where('groupe_id', $groupe->id)->first();
    $this->assertDatabaseHas('projet_conclusions', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
    ]);
});

test('chaque membre a sa propre conclusion distincte', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $e1, 'etudiant2' => $e2] = creerScenario();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/conclusion";

    $this->actingAs($e1)->putJson($url, ['contenu' => '<p>Conclusion de E1</p>']);
    $this->actingAs($e2)->putJson($url, ['contenu' => '<p>Conclusion de E2</p>']);

    $projet = ProjetRecherche::where('groupe_id', $groupe->id)->first();

    expect(ProjetConclusion::where('projet_id', $projet->id)->count())->toBe(2);
});

test('un étudiant extérieur ne peut pas enregistrer une conclusion', function () {
    ['classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etranger->id);

    $this->actingAs($etranger)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/conclusion", [
            'contenu' => '<p>Intrusion</p>',
        ])
        ->assertForbidden();
});

// ─── Export ───────────────────────────────────────────────────────────────────

test('un membre peut exporter le projet de groupe en PDF', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    // Le controller fait firstOrFail() — il faut qu'un projet existe
    ProjetRecherche::create(['groupe_id' => $groupe->id]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('un membre peut exporter le projet de groupe en Word', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    // Le controller fait firstOrFail() — il faut qu'un projet existe
    ProjetRecherche::create(['groupe_id' => $groupe->id]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/word")
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
});

// ─── Verrouillage du document ──────────────────────────────────────────────────

test("l'enseignant peut verrouiller un document", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/verrouille")
        ->assertOk()
        ->assertJson(['message' => 'toggled', 'verrouille' => true]);

    $this->assertDatabaseHas('projets_recherche', ['groupe_id' => $groupe->id, 'verrouille' => true]);
});

test('le toggle déverrouille un document déjà verrouillé', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $groupe->id, 'verrouille' => true]);

    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/verrouille")
        ->assertOk()
        ->assertJson(['verrouille' => false]);
});

test("un étudiant ne peut pas verrouiller un document", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/verrouille")
        ->assertForbidden();
});

test("un étudiant ne peut pas modifier un projet verrouillé", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $groupe->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets", [
            'titre_projet' => 'Modification bloquée',
        ])
        ->assertForbidden();
});

test('peutEditer est false quand le document est verrouillé', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $groupe->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('peutEditer', false)
            ->where('verrouille', true)
        );
});

// ─── Visibilité des corrections ────────────────────────────────────────────────

test("l'enseignant peut activer la visibilité des corrections", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/correction-visible")
        ->assertOk()
        ->assertJson(['message' => 'toggled', 'correction_visible' => true]);
});

test("un étudiant ne peut pas modifier la visibilité des corrections", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/correction-visible")
        ->assertForbidden();
});

test("un étudiant ne voit pas les annotations de type correction si correction_visible est false", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => false]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Commentaire visible',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction masquée',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
            ->where('annotationsParChamp.introduction_amener.0.type', 'commentaire')
        );
});

test("un étudiant voit les corrections si correction_visible est true", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => true]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction visible',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
            ->where('annotationsParChamp.introduction_amener.0.type', 'correction')
        );
});

test("l'enseignant voit toujours toutes les annotations, peu importe correction_visible", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => false]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
        );
});

// ─── Remise de travail ─────────────────────────────────────────────────────────

test('un membre peut remettre son travail', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/remettre")
        ->assertOk()
        ->assertJsonStructure(['message', 'remis_le'])
        ->assertJson(['message' => 'remis']);

    $projet = ProjetRecherche::where('groupe_id', $groupe->id)->first();
    expect($projet->remis_le)->not->toBeNull();
});

test("un étudiant hors groupe ne peut pas remettre le travail", function () {
    ['classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etranger->id);

    $this->actingAs($etranger)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/remettre")
        ->assertForbidden();
});

test('la remise est refusée si le document est verrouillé', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $groupe->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/remettre")
        ->assertForbidden();
});

test('une deuxième remise est refusée sans remises multiples', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'remis_le' => now(),
        'remises_multiples' => false,
    ]);

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/remettre")
        ->assertUnprocessable();
});

test('une deuxième remise est autorisée avec remises multiples', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'remis_le' => now()->subDay(),
        'remises_multiples' => true,
    ]);

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/remettre")
        ->assertOk()
        ->assertJson(['message' => 'remis']);
});

// ─── Paramètres de remise ──────────────────────────────────────────────────────

test("l'enseignant peut configurer la date de remise et les remises multiples", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/parametres-remise", [
            'date_remise' => '2026-04-15',
            'remises_multiples' => true,
        ])
        ->assertOk()
        ->assertJson(['message' => 'saved', 'remises_multiples' => true]);

    $this->assertDatabaseHas('projets_recherche', [
        'groupe_id' => $groupe->id,
        'remises_multiples' => true,
    ]);
});

test("un étudiant ne peut pas modifier les paramètres de remise", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/parametres-remise", [
            'remises_multiples' => true,
        ])
        ->assertForbidden();
});

// ─── Publication des notes ─────────────────────────────────────────────────────

test("un étudiant ne voit pas sa note si correction_visible est false", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => false]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 3,
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('correctionVisible', false)
            ->where("noteFinaleParEtudiant.{$etudiant->id}", null)
        );
});

test("un étudiant voit sa note quand correction_visible est true", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => true]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 4,
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('correctionVisible', true)
            ->where("noteFinaleParEtudiant.{$etudiant->id}", 10) // 4/4 * 10 = 10
        );
});

test("l'enseignant voit toujours les notes même si correction_visible est false", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'correction_visible' => false]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 2,
    ]);

    $this->actingAs($enseignant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("noteFinaleParEtudiant.{$etudiant->id}", 5) // 2/4 * 10 = 5
        );
});

test("publier les corrections active correction_visible et le toggle le désactive", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerScenario();

    // Premier appel : active
    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/correction-visible")
        ->assertJson(['correction_visible' => true]);

    // Second appel : désactive
    $this->actingAs($enseignant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/correction-visible")
        ->assertJson(['correction_visible' => false]);
});
