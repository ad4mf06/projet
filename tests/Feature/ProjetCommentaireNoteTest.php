<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetCommentaire;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte de test avec un enseignant, une classe, un groupe et deux étudiants membres.
 *
 * @return array{enseignant: User, classe: Classe, groupe: Groupe, etudiant: User, projet: ProjetRecherche}
 */
function creerContexteProjet(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'code' => '330-2E1',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    $groupe->membres()->attach($etudiant->id);

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id]);

    return compact('enseignant', 'classe', 'groupe', 'etudiant', 'projet');
}

// ─── Commentaires — autorisation ──────────────────────────────────────────────

test("l'enseignant peut créer un commentaire sur un champ du projet", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => 'Pensez à contextualiser davantage.',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'id', 'contenu']);

    $this->assertDatabaseHas('projet_commentaires', [
        'champ' => 'introduction_amener',
        'contenu' => 'Pensez à contextualiser davantage.',
    ]);
});

test('un étudiant ne peut pas créer un commentaire', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => 'Tentative non autorisée.',
        ])
        ->assertForbidden();
});

// ─── Commentaires — upsert ────────────────────────────────────────────────────

test('un deuxième PUT sur le même champ met à jour le commentaire sans créer de doublon', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'projet' => $projet] = creerContexteProjet();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires";

    $this->actingAs($enseignant)->putJson($url, [
        'champ' => 'dev_1_contenu',
        'contenu' => 'Premier commentaire.',
    ]);

    $this->actingAs($enseignant)->putJson($url, [
        'champ' => 'dev_1_contenu',
        'contenu' => 'Commentaire mis à jour.',
    ])->assertOk();

    expect(ProjetCommentaire::where('projet_id', $projet->id)->where('champ', 'dev_1_contenu')->count())->toBe(1);
    $this->assertDatabaseHas('projet_commentaires', ['contenu' => 'Commentaire mis à jour.']);
});

test("l'enseignant peut supprimer un commentaire", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'projet' => $projet] = creerContexteProjet();

    $commentaire = ProjetCommentaire::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_poser',
        'contenu' => 'À retravailler.',
        'created_by' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires/{$commentaire->id}")
        ->assertOk()
        ->assertJson(['message' => 'deleted']);

    $this->assertDatabaseMissing('projet_commentaires', ['id' => $commentaire->id]);
});

// ─── Commentaires — validation ────────────────────────────────────────────────

test('un champ non autorisé retourne une erreur de validation', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires", [
            'champ' => 'champ_inexistant',
            'contenu' => 'Test.',
        ])
        ->assertUnprocessable();
});

test('le contenu du commentaire est obligatoire', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => '',
        ])
        ->assertUnprocessable();
});

// ─── Notes — autorisation ─────────────────────────────────────────────────────

test("l'enseignant peut sauvegarder une note par étudiant", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant, 'projet' => $projet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes", [
            'critere' => 'developpement_faits',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'noteFinaleParEtudiant']);

    $this->assertDatabaseHas('projet_notes', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'developpement_faits',
        'note' => 4,
    ]);
});

test('un étudiant ne peut pas sauvegarder une note', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes", [
            'critere' => 'developpement_faits',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertForbidden();
});

test('user_id est obligatoire pour sauvegarder une note', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes", [
            'critere' => 'ecriture',
            'note' => 3,
            // user_id absent
        ])
        ->assertUnprocessable();
});

// ─── Notes — validation ───────────────────────────────────────────────────────

it('accepte uniquement les notes 0, 2, 3 et 4', function (int $note) {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes", [
            'critere' => 'ecriture',
            'note' => $note,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
})->with([1, 5, -1, 99]);

test('un critère inexistant retourne une erreur de validation', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes", [
            'critere' => 'critere_inexistant',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
});

// ─── Notes — upsert ───────────────────────────────────────────────────────────

test('un deuxième PUT sur le même critère et étudiant met à jour la note sans créer de doublon', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant, 'projet' => $projet] = creerContexteProjet();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes";

    $this->actingAs($enseignant)->putJson($url, ['critere' => 'ecriture', 'note' => 3, 'user_id' => $etudiant->id]);
    $this->actingAs($enseignant)->putJson($url, ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id])->assertOk();

    expect(
        ProjetNote::where('projet_id', $projet->id)
            ->where('critere', 'ecriture')
            ->where('user_id', $etudiant->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('projet_notes', ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id]);
});

// ─── Note finale calculée ─────────────────────────────────────────────────────

test('la note finale par étudiant est correctement calculée sur 100', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant, 'projet' => $projet] = creerContexteProjet();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/notes";

    // Tous les critères à "Excellent" (4) pour cet étudiant → note finale = 100
    foreach (array_keys(ProjetNote::CRITERES) as $critere) {
        $this->actingAs($enseignant)->putJson($url, ['critere' => $critere, 'note' => 4, 'user_id' => $etudiant->id]);
    }

    $response = $this->actingAs($enseignant)
        ->putJson($url, ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id])
        ->assertOk();

    $noteFinaleParEtudiant = $response->json('noteFinaleParEtudiant');
    expect((float) $noteFinaleParEtudiant[$etudiant->id])->toBe(100.0);
});

test('la note finale est nulle si aucune note n\'a été saisie pour cet étudiant', function () {
    ['projet' => $projet, 'etudiant' => $etudiant] = creerContexteProjet();

    expect(ProjetNote::noteFinale($projet, $etudiant))->toBeNull();
});
