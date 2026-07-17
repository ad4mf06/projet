<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeImage;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le contexte complet cours → classe → groupe → typeProjet musée → section → projet.
 *
 * @return array{Cours, Classe, Groupe, TypeProjet, TypeProjetSection, ProjetRecherche, User, User}
 */
function creerContexteBloc(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours blocs test',
        'code' => '330-BL',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create([
        'code' => 'H2025',
        'cours_id' => $cours->id,
    ]);

    $groupe = Groupe::create([
        'numero' => 1,
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée blocs',
        'type' => 'musee',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section test',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant];
}

// ─── store ─────────────────────────────────────────────────────────────────────

test('un membre peut créer un bloc texte dans une section', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $this->actingAs($etudiant)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs",
            ['type' => 'texte'],
        )
        ->assertRedirect();

    $bloc = MuseeBloc::where('projet_recherche_id', $projet->id)
        ->where('section_id', $section->id)
        ->first();

    expect($bloc)->not->toBeNull();
    expect($bloc->type)->toBe('texte');
    expect($bloc->contenu)->toBe(['html' => '']);
    expect($bloc->ordre)->toBe(1);
});

test("l'ordre des blocs est auto-incrémenté à chaque ajout", function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $url = "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs";

    $this->actingAs($etudiant)->post($url, ['type' => 'texte']);
    $this->actingAs($etudiant)->post($url, ['type' => 'image']);
    $this->actingAs($etudiant)->post($url, ['type' => 'separateur']);

    $ordres = MuseeBloc::where('projet_recherche_id', $projet->id)
        ->orderBy('ordre')
        ->pluck('ordre')
        ->toArray();

    expect($ordres)->toBe([1, 2, 3]);
});

test('store rejette un type de bloc invalide', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $this->actingAs($etudiant)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs",
            ['type' => 'invalide'],
        )
        ->assertSessionHasErrors('type');
});

test('un non-membre ne peut pas créer de bloc (403)', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($intrus)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs",
            ['type' => 'texte'],
        )
        ->assertForbidden();
});

// ─── update ────────────────────────────────────────────────────────────────────

test('un membre peut mettre à jour le contenu HTML d\'un bloc texte', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'texte',
        'contenu' => ['html' => ''],
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            ['html' => '<p>Mon texte</p>'],
        )
        ->assertRedirect();

    expect($bloc->fresh()->contenu)->toBe(['html' => '<p>Mon texte</p>']);
});

test('un membre peut lier une image à un bloc image', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'image',
        'contenu' => ['image_id' => null, 'legende' => '', 'alt' => ''],
        'ordre' => 1,
    ]);

    // On crée une MuseeImage fictive pour avoir un ID valide
    $image = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/test.jpg',
        'alt' => 'Image test',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 1024,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            ['image_id' => $image->id, 'alt' => 'Alt test', 'legende' => 'Légende test'],
        )
        ->assertRedirect();

    // Ordre : image_id, legende, alt — correspond à validerContenu() dans MuseeBlocController
    expect($bloc->fresh()->contenu)->toBe([
        'image_id' => $image->id,
        'legende' => 'Légende test',
        'alt' => 'Alt test',
    ]);
});

// ─── destroy ──────────────────────────────────────────────────────────────────

test('un membre peut supprimer un bloc et renuméroter les suivants', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $bloc1 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => ''], 'ordre' => 1]);
    $bloc2 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => ''], 'ordre' => 2]);
    $bloc3 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => ''], 'ordre' => 3]);

    $this->actingAs($etudiant)
        ->delete(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc2->id}",
        )
        ->assertRedirect();

    expect(MuseeBloc::find($bloc2->id))->toBeNull();

    // bloc3 doit être renuméroté à 2
    expect($bloc3->fresh()->ordre)->toBe(2);
    // bloc1 reste à 1
    expect($bloc1->fresh()->ordre)->toBe(1);
});

// ─── reorder ──────────────────────────────────────────────────────────────────

test('un membre peut réordonner les blocs', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant] = creerContexteBloc();

    $bloc1 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => 'A'], 'ordre' => 1]);
    $bloc2 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => 'B'], 'ordre' => 2]);
    $bloc3 = MuseeBloc::create(['projet_recherche_id' => $projet->id, 'section_id' => $section->id, 'type' => 'texte', 'contenu' => ['html' => 'C'], 'ordre' => 3]);

    // Réordonner : C, A, B
    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/reorder",
            ['ordre' => [$bloc3->id, $bloc1->id, $bloc2->id]],
        )
        ->assertRedirect();

    expect($bloc3->fresh()->ordre)->toBe(1);
    expect($bloc1->fresh()->ordre)->toBe(2);
    expect($bloc2->fresh()->ordre)->toBe(3);
});
