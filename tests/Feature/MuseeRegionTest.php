<?php

use App\Models\Cours;
use App\Models\MuseeRegion;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours minimal pour l'enseignant donné.
 */
function creerCoursRegion(User $enseignant): Cours
{
    return Cours::create([
        'nom_cours' => 'Cours région test',
        'code' => '330-R1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
}

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une région pour son cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/musee-regions", ['nom' => 'Amérique du Nord'])
        ->assertRedirect();

    $region = MuseeRegion::where('cours_id', $cours->id)->first();
    expect($region)->not->toBeNull();
    expect($region->nom)->toBe('Amérique du Nord');
    expect($region->ordre)->toBe(1);
    expect($region->enseignant_id)->toBe($enseignant->id);
});

test("l'ordre des régions est incrémenté automatiquement", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);

    $this->actingAs($enseignant)->post("/cours/{$cours->id}/musee-regions", ['nom' => 'Europe']);
    $this->actingAs($enseignant)->post("/cours/{$cours->id}/musee-regions", ['nom' => 'Asie']);

    expect(MuseeRegion::where('cours_id', $cours->id)->max('ordre'))->toBe(2);
});

test("un enseignant ne peut pas créer une région pour le cours d'un autre (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    $coursAutre = creerCoursRegion($autre);

    $this->actingAs($enseignant)
        ->post("/cours/{$coursAutre->id}/musee-regions", ['nom' => 'Europe'])
        ->assertForbidden();
});

test('store rejette un nom de région vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/musee-regions", ['nom' => ''])
        ->assertSessionHasErrors('nom');
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le nom d'une région", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);
    $region = MuseeRegion::create([
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Ancien nom',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/musee-regions/{$region->id}", ['nom' => 'Afrique subsaharienne'])
        ->assertRedirect();

    expect($region->fresh()->nom)->toBe('Afrique subsaharienne');
});

test("une région d'un autre cours est refusée à la mise à jour (404)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours1 = creerCoursRegion($enseignant);
    $cours2 = Cours::create([
        'nom_cours' => 'Cours 2', 'code' => '330-R2', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $region = MuseeRegion::create([
        'cours_id' => $cours2->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Europe',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours1->id}/musee-regions/{$region->id}", ['nom' => 'Autre'])
        ->assertNotFound();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer une région", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);
    $region = MuseeRegion::create([
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Europe',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/musee-regions/{$region->id}")
        ->assertRedirect();

    expect(MuseeRegion::find($region->id))->toBeNull();
});

test('supprimer une région renuméroter les suivantes', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);

    $r1 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'A', 'ordre' => 1]);
    $r2 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'B', 'ordre' => 2]);
    $r3 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'C', 'ordre' => 3]);

    $this->actingAs($enseignant)->delete("/cours/{$cours->id}/musee-regions/{$r1->id}");

    expect($r2->fresh()->ordre)->toBe(1);
    expect($r3->fresh()->ordre)->toBe(2);
});

// ─── Reorder ──────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les régions", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursRegion($enseignant);

    $r1 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'A', 'ordre' => 1]);
    $r2 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'B', 'ordre' => 2]);
    $r3 = MuseeRegion::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom' => 'C', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/musee-regions/reorder", [
            'ordre' => [$r3->id, $r1->id, $r2->id],
        ])
        ->assertRedirect();

    expect($r3->fresh()->ordre)->toBe(1);
    expect($r1->fresh()->ordre)->toBe(2);
    expect($r2->fresh()->ordre)->toBe(3);
});
