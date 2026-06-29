<?php

use App\Models\Cours;
use App\Models\MuseePeriode;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours minimal pour l'enseignant donné.
 */
function creerCoursPeriode(User $enseignant): Cours
{
    return Cours::create([
        'nom_cours' => 'Cours période test',
        'code' => '330-P1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
}

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une période pour son cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/musee-periodes", ['nom' => 'Antiquité'])
        ->assertRedirect();

    $periode = MuseePeriode::where('cours_id', $cours->id)->first();
    expect($periode)->not->toBeNull();
    expect($periode->nom)->toBe('Antiquité');
    expect($periode->ordre)->toBe(1);
    expect($periode->enseignant_id)->toBe($enseignant->id);
});

test("l'ordre des périodes est incrémenté automatiquement", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);

    $this->actingAs($enseignant)->post("/cours/{$cours->id}/musee-periodes", ['nom' => 'Antiquité']);
    $this->actingAs($enseignant)->post("/cours/{$cours->id}/musee-periodes", ['nom' => 'Moyen Âge']);

    expect(MuseePeriode::where('cours_id', $cours->id)->max('ordre'))->toBe(2);
});

test("un enseignant ne peut pas créer une période pour le cours d'un autre (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    $coursAutre = creerCoursPeriode($autre);

    $this->actingAs($enseignant)
        ->post("/cours/{$coursAutre->id}/musee-periodes", ['nom' => 'Antiquité'])
        ->assertForbidden();
});

test('store rejette un nom de période vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/musee-periodes", ['nom' => ''])
        ->assertSessionHasErrors('nom');
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le nom d'une période", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);
    $periode = MuseePeriode::create([
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Ancien',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/musee-periodes/{$periode->id}", ['nom' => 'Antiquité'])
        ->assertRedirect();

    expect($periode->fresh()->nom)->toBe('Antiquité');
});

test("une période d'un autre cours est refusée à la mise à jour (404)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours1 = creerCoursPeriode($enseignant);
    $cours2 = Cours::create([
        'nom_cours' => 'Cours 2', 'code' => '330-P2', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $periode = MuseePeriode::create([
        'cours_id' => $cours2->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Antiquité',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours1->id}/musee-periodes/{$periode->id}", ['nom' => 'Autre'])
        ->assertNotFound();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer une période", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);
    $periode = MuseePeriode::create([
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Antiquité',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/musee-periodes/{$periode->id}")
        ->assertRedirect();

    expect(MuseePeriode::find($periode->id))->toBeNull();
});

test('supprimer une période renuméroter les suivantes', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);

    $p1 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'A', 'ordre' => 1]);
    $p2 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'B', 'ordre' => 2]);
    $p3 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'C', 'ordre' => 3]);

    $this->actingAs($enseignant)->delete("/cours/{$cours->id}/musee-periodes/{$p1->id}");

    expect($p2->fresh()->ordre)->toBe(1);
    expect($p3->fresh()->ordre)->toBe(2);
});

// ─── Reorder ──────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les périodes", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursPeriode($enseignant);

    $p1 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'A', 'ordre' => 1]);
    $p2 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'B', 'ordre' => 2]);
    $p3 = MuseePeriode::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'C', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/musee-periodes/reorder", [
            'ordre' => [$p3->id, $p1->id, $p2->id],
        ])
        ->assertRedirect();

    expect($p3->fresh()->ordre)->toBe(1);
    expect($p1->fresh()->ordre)->toBe(2);
    expect($p2->fresh()->ordre)->toBe(3);
});
