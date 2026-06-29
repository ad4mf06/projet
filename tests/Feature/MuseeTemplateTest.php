<?php

use App\Models\Cours;
use App\Models\MuseeTemplate;
use App\Models\TypeProjet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours minimal pour l'enseignant donné.
 */
function creerCoursMusee(User $enseignant): Cours
{
    return Cours::create([
        'nom_cours' => 'Cours musée test',
        'code' => '330-M1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
}

/**
 * Crée un TypeProjet de type musée et retourne le couple [cours, typeProjet].
 *
 * @return array{Cours, TypeProjet}
 */
function creerTypeProjetMusee(User $enseignant): array
{
    $cours = creerCoursMusee($enseignant);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Mon musée',
        'type' => 'musee',
        'accessible' => false,
    ]);

    return [$cours, $typeProjet];
}

// ─── Observer — création automatique du MuseeTemplate ─────────────────────────

test("l'observer crée un MuseeTemplate automatiquement lors de la création d'un TypeProjet musée", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    expect(MuseeTemplate::where('type_projet_id', $typeProjet->id)->exists())->toBeTrue();
});

test("l'observer ne crée pas de MuseeTemplate pour un TypeProjet standard", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet standard',
        'type' => 'standard',
        'accessible' => false,
    ]);

    expect(MuseeTemplate::where('type_projet_id', $typeProjet->id)->exists())->toBeFalse();
});

test('le MuseeTemplate créé automatiquement a les valeurs par défaut attendues', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();

    expect($template->font_titre_page)->toBe('Georgia');
    expect($template->font_corps)->toBe('Arial');
    expect($template->couleur_fond)->toBe('#F5EFE0');
    expect($template->couleur_titre)->toBe('#1A2744');
    expect($template->theme)->toBe('clair');
});

// ─── TypeProjetController::store — type musée ─────────────────────────────────

test("la création d'un TypeProjet de type musée redirige vers le template", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $response = $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
            'nom' => 'Musée de la Nouvelle-France',
            'type' => 'musee',
        ]);

    $response->assertRedirectContains('musee-template');
});

test('la création standard redirige vers edit (comportement inchangé)', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $response = $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet recherche',
            'type' => 'standard',
        ]);

    $response->assertRedirectContains('edit');
});

// ─── MuseeTemplateController::edit ────────────────────────────────────────────

test("l'enseignant peut accéder à la page de personnalisation du template", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Musee/Template/Edit')
            ->has('template')
            ->where('typeProjet.id', $typeProjet->id)
        );
});

test("un enseignant ne peut pas accéder au template d'un autre enseignant (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    [$coursAutre, $typeProjetAutre] = creerTypeProjetMusee($autre);

    $this->actingAs($enseignant)
        ->get("/cours/{$coursAutre->id}/types-projets/{$typeProjetAutre->id}/musee-template")
        ->assertForbidden();
});

test('la page template retourne 404 pour un TypeProjet standard', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);
    $tp = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Standard',
        'type' => 'standard',
        'accessible' => false,
    ]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$tp->id}/musee-template")
        ->assertNotFound();
});

// ─── MuseeTemplateController::update ──────────────────────────────────────────

test("l'enseignant peut mettre à jour le template visuel", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template", [
            'font_titre_page' => 'Playfair Display',
            'font_sous_titre' => 'Lora',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Open Sans',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'palette' => 'contemporain',
            'theme' => 'clair',
        ])
        ->assertRedirect();

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();
    expect($template->font_titre_page)->toBe('Playfair Display');
    expect($template->couleur_fond)->toBe('#FFFFFF');
    expect($template->palette)->toBe('contemporain');
});

test('la mise à jour du template rejette un thème invalide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template", [
            'font_titre_page' => 'Georgia',
            'font_sous_titre' => 'Georgia',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Arial',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'theme' => 'invalide',
        ])
        ->assertSessionHasErrors('theme');
});

test("un enseignant ne peut pas modifier le template d'un autre (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    [$coursAutre, $typeProjetAutre] = creerTypeProjetMusee($autre);

    $this->actingAs($enseignant)
        ->put("/cours/{$coursAutre->id}/types-projets/{$typeProjetAutre->id}/musee-template", [
            'font_titre_page' => 'Georgia',
            'font_sous_titre' => 'Georgia',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Arial',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'theme' => 'clair',
        ])
        ->assertForbidden();
});

test('toCssVariables retourne le bon tableau de variables', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();
    $vars = $template->toCssVariables();

    expect($vars)->toHaveKey('--musee-font-titre-page');
    expect($vars)->toHaveKey('--musee-couleur-fond');
    expect($vars['--musee-font-titre-page'])->toBe('Georgia');
    expect($vars['--musee-couleur-fond'])->toBe('#F5EFE0');
});
