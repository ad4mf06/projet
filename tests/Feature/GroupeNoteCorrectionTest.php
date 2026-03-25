<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le contexte minimal : enseignant + classe + étudiant + groupe + note.
 *
 * @return array{enseignant: User, classe: Classe, etudiant: User, groupe: Groupe, note: GroupeNote}
 */
function creerContexteCorrection(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Cours test corrections',
        'description' => 'Test',
        'heures_par_semaine' => 2,
        'code' => '330-CORR',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    $note = GroupeNote::create([
        'groupe_id' => $groupe->id,
        'user_id' => $etudiant->id,
        'contenu' => 'Voici le texte de la note étudiant.',
    ]);

    return compact('enseignant', 'classe', 'etudiant', 'groupe', 'note');
}

// ─── upsertNoteCorrection() ────────────────────────────────────────────────────

test('upsertNoteCorrection() crée une correction et met à jour le HTML de la note', function () {
    $ctx = creerContexteCorrection();
    $commentaireId = '550e8400-e29b-41d4-a716-446655440000';
    $noteHtml = '<p>Voici le texte <mark data-comment-id="'.$commentaireId.'" class="comment-mark">de la</mark> note.</p>';

    $this->actingAs($ctx['enseignant'])
        ->put("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => $commentaireId,
            'contenu' => 'Mauvaise formulation — à corriger.',
            'note_html' => $noteHtml,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_note_corrections', [
        'note_id' => $ctx['note']->id,
        'commentaire_id' => $commentaireId,
        'contenu' => 'Mauvaise formulation — à corriger.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    expect($ctx['note']->fresh()->contenu)->toBe($noteHtml);
});

test('upsertNoteCorrection() met à jour une correction existante sans doublon (upsert)', function () {
    $ctx = creerContexteCorrection();
    $commentaireId = '550e8400-e29b-41d4-a716-446655440001';

    GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => $commentaireId,
        'contenu' => 'Version initiale.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->put("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => $commentaireId,
            'contenu' => 'Version corrigée.',
            'note_html' => '<p>html mis à jour</p>',
        ])
        ->assertRedirect();

    expect(
        GroupeNoteCorrection::where('note_id', $ctx['note']->id)
            ->where('commentaire_id', $commentaireId)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('groupe_note_corrections', [
        'commentaire_id' => $commentaireId,
        'contenu' => 'Version corrigée.',
    ]);
});

test('upsertNoteCorrection() refuse un étudiant (redirige — middleware rôle)', function () {
    $ctx = creerContexteCorrection();

    // Le middleware EnsureRole redirige les rôles non autorisés vers /dashboard
    $this->actingAs($ctx['etudiant'])
        ->put("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-quelconque',
            'contenu' => 'Tentative étudiante.',
            'note_html' => '<p>html</p>',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_note_corrections', ['note_id' => $ctx['note']->id]);
});

test("upsertNoteCorrection() refuse un enseignant d'une autre classe (403)", function () {
    $ctx = creerContexteCorrection();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-quelconque',
            'contenu' => 'Correction non autorisée.',
            'note_html' => '<p>html</p>',
        ])
        ->assertForbidden();
});

test('upsertNoteCorrection() rejette une requête sans contenu (validation)', function () {
    $ctx = creerContexteCorrection();

    $this->actingAs($ctx['enseignant'])
        ->put("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-valide',
            'contenu' => '',
            'note_html' => '<p>html</p>',
        ])
        ->assertInvalid(['contenu']);
});

// ─── destroyNoteCorrection() ───────────────────────────────────────────────────

test('destroyNoteCorrection() supprime la correction et met à jour le HTML de la note', function () {
    $ctx = creerContexteCorrection();

    $correction = GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440002',
        'contenu' => 'À supprimer.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $htmlNettoyé = '<p>Voici le texte de la note étudiant.</p>';

    $this->actingAs($ctx['enseignant'])
        ->delete("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections/{$correction->id}", [
            'note_html' => $htmlNettoyé,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_note_corrections', ['id' => $correction->id]);
    expect($ctx['note']->fresh()->contenu)->toBe($htmlNettoyé);
});

test('destroyNoteCorrection() refuse un étudiant (redirige — middleware rôle)', function () {
    $ctx = creerContexteCorrection();

    $correction = GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440003',
        'contenu' => 'Correction test.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    // Le middleware EnsureRole redirige les rôles non autorisés vers /dashboard
    $this->actingAs($ctx['etudiant'])
        ->delete("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections/{$correction->id}", [
            'note_html' => '<p>html</p>',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_note_corrections', ['id' => $correction->id]);
});

test("destroyNoteCorrection() retourne 404 si la correction n'appartient pas à la note", function () {
    $ctx = creerContexteCorrection();

    $autreNote = GroupeNote::create([
        'groupe_id' => $ctx['groupe']->id,
        'user_id' => $ctx['etudiant']->id,
        'contenu' => 'Autre note.',
    ]);

    $correctionDAutreNote = GroupeNoteCorrection::create([
        'note_id' => $autreNote->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440004',
        'contenu' => 'Correction sur autre note.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->delete("/groupes/{$ctx['groupe']->id}/notes/{$ctx['note']->id}/corrections/{$correctionDAutreNote->id}", [
            'note_html' => '<p>html</p>',
        ])
        ->assertNotFound();
});
