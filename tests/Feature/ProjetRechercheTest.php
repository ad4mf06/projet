<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\User;

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

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('un membre peut exporter le projet de groupe en Word', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/word")
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
});
