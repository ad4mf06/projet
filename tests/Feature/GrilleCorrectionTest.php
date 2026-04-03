<?php

use App\Models\Classe;
use App\Models\GrilleCorrection;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant, une classe et une grille de correction valide rattachée à la classe.
 *
 * @return array{enseignant: User, classe: Classe, grille: GrilleCorrection}
 */
function creerContexteGrille(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Cours test',
        'description' => 'Test',
        'code' => '330-XT',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $grille = GrilleCorrection::create([
        'classe_id' => $classe->id,
        'nom' => 'Grille test',
        'description' => 'Description test',
    ]);

    $grille->criteres()->createMany([
        ['label' => 'Compréhension', 'ponderation' => 60, 'ordre' => 0],
        ['label' => 'Rédaction',     'ponderation' => 40, 'ordre' => 1],
    ]);

    return compact('enseignant', 'classe', 'grille');
}

/**
 * Retourne un payload valide pour créer une grille (somme = 100).
 *
 * @return array<string, mixed>
 */
function payloadGrilleValide(): array
{
    return [
        'nom' => 'Ma grille',
        'description' => 'Une description',
        'criteres' => [
            ['label' => 'Argumentation', 'ponderation' => 70],
            ['label' => 'Présentation',  'ponderation' => 30],
        ],
        'malus' => [],
    ];
}

// ─── Edit (page création/édition) ─────────────────────────────────────────────

test("l'enseignant peut accéder à la page de gestion de la grille de sa classe", function () {
    ['enseignant' => $enseignant, 'classe' => $classe] = creerContexteGrille();

    $this->actingAs($enseignant)
        ->get("/classes/{$classe->id}/grille")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Classes/Grille')
        );
});

test("un enseignant ne peut pas accéder à la grille d'une classe qui ne lui appartient pas", function () {
    ['classe' => $classe] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->get("/classes/{$classe->id}/grille")
        ->assertForbidden();
});

test('un étudiant est redirigé depuis la page de gestion de la grille (rôle insuffisant)', function () {
    ['classe' => $classe] = creerContexteGrille();

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/grille")
        ->assertRedirect();
});

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une grille de correction pour sa classe", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Sciences',
        'description' => 'Test',
        'code' => '420-A1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->post("/classes/{$classe->id}/grille", payloadGrilleValide())
        ->assertRedirect("/classes/{$classe->id}");

    $this->assertDatabaseHas('grilles_correction', [
        'nom' => 'Ma grille',
        'classe_id' => $classe->id,
    ]);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Argumentation', 'ponderation' => 70]);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Présentation',  'ponderation' => 30]);
});

test("l'enseignant peut créer une grille avec des malus", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Maths',
        'description' => 'Test',
        'code' => '201-M1',
        'groupe' => '02',
        'enseignant_id' => $enseignant->id,
    ]);

    $payload = payloadGrilleValide();
    $payload['malus'] = [
        ['label' => 'Fautes de français', 'deduction' => 2, 'description' => ''],
    ];

    $this->actingAs($enseignant)
        ->post("/classes/{$classe->id}/grille", $payload)
        ->assertRedirect("/classes/{$classe->id}");

    $this->assertDatabaseHas('grille_malus', [
        'label' => 'Fautes de français',
        'deduction' => 2,
    ]);
});

test('la somme des pondérations doit être exactement 100', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Physique',
        'description' => 'Test',
        'code' => '203-P1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'][1]['ponderation'] = 20; // total = 90

    $this->actingAs($enseignant)
        ->postJson("/classes/{$classe->id}/grille", $payload)
        ->assertUnprocessable();
});

test('chaque critère doit avoir un libellé non vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Chimie',
        'description' => 'Test',
        'code' => '202-C1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'][0]['label'] = '';

    $this->actingAs($enseignant)
        ->postJson("/classes/{$classe->id}/grille", $payload)
        ->assertUnprocessable();
});

test('la liste de critères ne peut pas être vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Biologie',
        'description' => 'Test',
        'code' => '101-B1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'] = [];

    $this->actingAs($enseignant)
        ->postJson("/classes/{$classe->id}/grille", $payload)
        ->assertUnprocessable();
});

test("l'enseignant ne peut pas créer une seconde grille si la classe en a déjà une", function () {
    ['enseignant' => $enseignant, 'classe' => $classe] = creerContexteGrille();

    // La classe a déjà une grille — le store doit être refusé (authorize = false)
    $this->actingAs($enseignant)
        ->post("/classes/{$classe->id}/grille", payloadGrilleValide())
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier la grille de sa classe", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'grille' => $grille] = creerContexteGrille();

    $critere = $grille->criteres->first();

    $this->actingAs($enseignant)
        ->put("/classes/{$classe->id}/grille", [
            'nom' => 'Grille modifiée',
            'criteres' => [
                ['id' => $critere->id, 'label' => 'Compréhension', 'ponderation' => 50],
                ['label' => 'Nouveau critère', 'ponderation' => 50],
            ],
            'malus' => [],
        ])
        ->assertRedirect("/classes/{$classe->id}");

    $this->assertDatabaseHas('grilles_correction', ['id' => $grille->id, 'nom' => 'Grille modifiée']);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Nouveau critère', 'ponderation' => 50]);
});

test('un critère retiré lors de la mise à jour est supprimé de la base', function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'grille' => $grille] = creerContexteGrille();

    $critereRetire = $grille->criteres->last(); // Rédaction (40 pts)

    $this->actingAs($enseignant)
        ->put("/classes/{$classe->id}/grille", [
            'nom' => 'Grille modifiée',
            'criteres' => [
                ['id' => $grille->criteres->first()->id, 'label' => 'Compréhension', 'ponderation' => 100],
            ],
            'malus' => [],
        ])
        ->assertRedirect("/classes/{$classe->id}");

    $this->assertDatabaseMissing('grille_criteres', ['id' => $critereRetire->id]);
});

test("un enseignant ne peut pas modifier la grille d'une classe qui ne lui appartient pas", function () {
    ['classe' => $classe] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put("/classes/{$classe->id}/grille", payloadGrilleValide())
        ->assertForbidden();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer la grille de sa classe", function () {
    ['enseignant' => $enseignant, 'classe' => $classe, 'grille' => $grille] = creerContexteGrille();

    $this->actingAs($enseignant)
        ->delete("/classes/{$classe->id}/grille")
        ->assertRedirect("/classes/{$classe->id}");

    $this->assertDatabaseMissing('grilles_correction', ['id' => $grille->id]);
});

test("un enseignant ne peut pas supprimer la grille d'une classe qui ne lui appartient pas", function () {
    ['classe' => $classe] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->delete("/classes/{$classe->id}/grille")
        ->assertForbidden();
});
