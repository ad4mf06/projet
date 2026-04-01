<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario de test complet :
 * - 1 enseignant avec 3 thématiques
 * - 1 classe
 * - 4 étudiants inscrits dans la classe
 *
 * @return array{enseignant: User, classe: Classe, t1: Thematique, t2: Thematique, t3: Thematique, alice: User, bob: User, claire: User, david: User}
 */
function creerContexteGroupe(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Cours test',
        'heures_par_semaine' => 3,
        'code' => '330-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $t1 = Thematique::create(['nom' => 'La Nouvelle-France',      'enseignant_id' => $enseignant->id]);
    $t2 = Thematique::create(['nom' => 'La Révolution tranquille', 'enseignant_id' => $enseignant->id]);
    $t3 = Thematique::create(['nom' => 'Les Premières Nations',    'enseignant_id' => $enseignant->id]);

    $alice = User::factory()->create(['role' => 'etudiant']);
    $bob = User::factory()->create(['role' => 'etudiant']);
    $claire = User::factory()->create(['role' => 'etudiant']);
    $david = User::factory()->create(['role' => 'etudiant']);

    $classe->etudiants()->attach([$alice->id, $bob->id, $claire->id, $david->id]);

    return compact('enseignant', 'classe', 't1', 't2', 't3', 'alice', 'bob', 'claire', 'david');
}

/**
 * Crée un groupe complet (membres + thématiques) pour les tests de show/update.
 *
 * @return array{groupe: Groupe, createur: User, membre: User}
 */
function creerGroupe(array $ctx, User $createur, User $membre, array $thematiques = []): Groupe
{
    $groupe = Groupe::create([
        'nom' => 'Équipe Test',
        'classe_id' => $ctx['classe']->id,
        'created_by' => $createur->id,
    ]);

    $groupe->membres()->attach([$createur->id, $membre->id]);

    if (! empty($thematiques)) {
        $groupe->thematiques()->attach(array_map(fn ($t) => $t->id, $thematiques));
    }

    return $groupe;
}

// ─── store() — création du groupe ─────────────────────────────────────────────

test('store() crée le groupe et remplit groupe_etudiant', function () {
    $ctx = creerContexteGroupe();

    $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", [
            'nom' => 'Mon groupe',
            'membres' => [$ctx['bob']->id],
        ])
        ->assertRedirect();

    $groupe = Groupe::where('classe_id', $ctx['classe']->id)->first();
    expect($groupe)->not->toBeNull();

    // Alice (créateur) et Bob doivent être dans groupe_etudiant
    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['alice']->id]);
    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['bob']->id]);
});

test('store() remplit groupe_thematique quand des thématiques sont sélectionnées', function () {
    $ctx = creerContexteGroupe();

    $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", [
            'nom' => 'Mon groupe',
            'membres' => [],
            'thematiques' => [$ctx['t1']->id, $ctx['t2']->id],
        ])
        ->assertRedirect();

    $groupe = Groupe::where('classe_id', $ctx['classe']->id)->first();

    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t2']->id]);
    expect($groupe->thematiques()->count())->toBe(2);
});

test('store() ajoute toujours le créateur comme membre même sans membres sélectionnés', function () {
    $ctx = creerContexteGroupe();

    $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", [
            'nom' => 'Solo',
            'membres' => [],
        ])
        ->assertRedirect();

    $groupe = Groupe::where('classe_id', $ctx['classe']->id)->first();

    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['alice']->id]);
    expect($groupe->membres()->count())->toBe(1);
});

test('store() filtre silencieusement les membres non inscrits dans la classe', function () {
    $ctx = creerContexteGroupe();
    $etranger = User::factory()->create(['role' => 'etudiant']); // non inscrit dans la classe

    $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", [
            'nom' => 'Groupe test',
            'membres' => [$ctx['bob']->id, $etranger->id],
        ])
        ->assertRedirect();

    $groupe = Groupe::where('classe_id', $ctx['classe']->id)->first();

    // Bob (inscrit) doit être présent, l'étranger (non inscrit) ne doit pas l'être
    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['bob']->id]);
    $this->assertDatabaseMissing('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $etranger->id]);
});

test('store() filtre silencieusement les thématiques hors classe', function () {
    $ctx = creerContexteGroupe();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematiqueHors = Thematique::create(['nom' => 'Thème étranger', 'enseignant_id' => $autreEnseignant->id]);

    $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", [
            'nom' => 'Groupe test',
            'membres' => [],
            'thematiques' => [$ctx['t1']->id, $thematiqueHors->id],
        ])
        ->assertRedirect();

    $groupe = Groupe::where('classe_id', $ctx['classe']->id)->first();

    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseMissing('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $thematiqueHors->id]);
});

test('store() refuse un étudiant non inscrit dans la classe (403)', function () {
    $ctx = creerContexteGroupe();
    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->post("/classes/{$ctx['classe']->id}/groupes", ['nom' => 'Groupe', 'membres' => []])
        ->assertForbidden();
});

test("store() refuse un étudiant déjà membre d'un groupe dans cette classe", function () {
    $ctx = creerContexteGroupe();
    creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $response = $this->actingAs($ctx['alice'])
        ->post("/classes/{$ctx['classe']->id}/groupes", ['nom' => 'Doublon', 'membres' => []]);

    $response->assertRedirect();
    // Le groupe original ne doit pas avoir été dupliqué
    expect(Groupe::where('classe_id', $ctx['classe']->id)->count())->toBe(1);
});

// ─── show() — consultation du groupe ──────────────────────────────────────────

test('show() est accessible à un membre du groupe', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $this->actingAs($ctx['alice'])
        ->get("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}")
        ->assertOk();
});

test("show() est accessible à l'enseignant de la classe", function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['enseignant'])
        ->get("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}")
        ->assertOk();
});

test('show() refuse un étudiant non membre du groupe (403)', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['claire']) // inscrite dans la classe mais pas dans ce groupe
        ->get("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}")
        ->assertForbidden();
});

test('show() retourne 404 si le groupe ne correspond pas à la classe', function () {
    $ctx = creerContexteGroupe();

    $autreClasse = Classe::create([
        'nom_cours' => 'Autre classe', 'code' => 'X999', 'groupe' => 'B',
        'heures_par_semaine' => 2, 'enseignant_id' => $ctx['enseignant']->id,
    ]);

    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->get("/classes/{$autreClasse->id}/groupes/{$groupe->id}")
        ->assertNotFound();
});

// ─── updateMembres() ──────────────────────────────────────────────────────────

test('updateMembres() ajoute un membre et remplit groupe_etudiant', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/membres", [
            'ajouter' => [$ctx['claire']->id],
            'retirer' => [],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['claire']->id]);
});

test('updateMembres() retire un membre de groupe_etudiant', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/membres", [
            'ajouter' => [],
            'retirer' => [$ctx['bob']->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['bob']->id]);
});

test('updateMembres() ne permet pas au créateur de se retirer', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/membres", [
            'ajouter' => [],
            'retirer' => [$ctx['alice']->id],
        ])
        ->assertRedirect();

    // Alice doit toujours être membre
    $this->assertDatabaseHas('groupe_etudiant', ['groupe_id' => $groupe->id, 'user_id' => $ctx['alice']->id]);
});

test('updateMembres() refuse un non-créateur (403)', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['bob']) // membre mais pas créateur
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/membres", [
            'ajouter' => [$ctx['claire']->id],
            'retirer' => [],
        ])
        ->assertForbidden();
});

// ─── updateThematiques() ──────────────────────────────────────────────────────

test('updateThematiques() remplace les thématiques et remplit groupe_thematique', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/thematiques", [
            'thematiques' => [$ctx['t2']->id, $ctx['t3']->id],
        ])
        ->assertRedirect();

    // t1 doit être retirée, t2 et t3 doivent être présentes
    $this->assertDatabaseMissing('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t2']->id]);
    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t3']->id]);
});

test('updateThematiques() accepte un tableau vide (supprime tout)', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1'], $ctx['t2']]);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/thematiques", [
            'thematiques' => [],
        ])
        ->assertRedirect();

    expect($groupe->thematiques()->count())->toBe(0);
});

test('updateThematiques() filtre les thématiques hors classe', function () {
    $ctx = creerContexteGroupe();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematiqueHors = Thematique::create(['nom' => 'Thème étranger', 'enseignant_id' => $autreEnseignant->id]);
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/thematiques", [
            'thematiques' => [$ctx['t1']->id, $thematiqueHors->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseMissing('groupe_thematique', ['groupe_id' => $groupe->id, 'thematique_id' => $thematiqueHors->id]);
});

test('updateThematiques() refuse un non-membre (403)', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['claire']) // inscrite dans la classe mais pas dans le groupe
        ->put("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}/thematiques", [
            'thematiques' => [$ctx['t1']->id],
        ])
        ->assertForbidden();
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("destroy() supprime le groupe — l'enseignant de la classe peut supprimer", function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $groupeId = $groupe->id;

    // Seul l'enseignant de la classe (ou admin) peut supprimer depuis la F4
    $this->actingAs($ctx['enseignant'])
        ->delete("/classes/{$ctx['classe']->id}/groupes/{$groupeId}")
        ->assertRedirect();

    $this->assertDatabaseMissing('groupes', ['id' => $groupeId]);
    $this->assertDatabaseMissing('groupe_etudiant', ['groupe_id' => $groupeId]);
    $this->assertDatabaseMissing('groupe_thematique', ['groupe_id' => $groupeId]);
});

test('destroy() refuse un enseignant étranger (403)', function () {
    $ctx = creerContexteGroupe();
    $groupe = creerGroupe($ctx, $ctx['alice'], $ctx['bob']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    // Un enseignant d'une autre classe ne peut pas supprimer
    $this->actingAs($autreEnseignant)
        ->delete("/classes/{$ctx['classe']->id}/groupes/{$groupe->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('groupes', ['id' => $groupe->id]);
});
