<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeImage;
use App\Models\MuseeMeta;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le contexte complet pour les tests Sprint 4.
 *
 * @return array{Cours, Classe, Groupe, TypeProjet, TypeProjetSection, ProjetRecherche, User, User}
 */
function creerContexteS4(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours S4 test',
        'code' => '330-S4',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['code' => 'H2025', 'cours_id' => $cours->id]);

    $groupe = Groupe::create([
        'numero' => 1,
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée S4',
        'type' => 'musee',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section S4',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return [$cours, $classe, $groupe, $typeProjet, $section, $projet, $enseignant, $etudiant];
}

// ─── Tâche 4.1 — Crop d'image ─────────────────────────────────────────────────

test('un membre peut mettre à jour le crop_data d\'une image via PATCH', function () {
    Storage::fake('public');
    [$cours, $classe, $groupe, $typeProjet, , $projet, , $etudiant] = creerContexteS4();

    $image = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/test.jpg',
        'alt' => 'Test',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 1024,
    ]);

    $this->actingAs($etudiant)
        ->patchJson(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-images/{$image->id}",
            ['crop_data' => ['x' => 60, 'y' => 30, 'width' => 400, 'height' => 300]],
        )
        ->assertOk()
        ->assertJsonFragment(['id' => $image->id]);

    expect($image->fresh()->crop_data)->toMatchArray(['x' => 60, 'y' => 30]);
});

test('le store d\'image accepte un crop_data JSON optionnel', function () {
    Storage::fake('public');
    [$cours, $classe, $groupe, $typeProjet, , $projet, , $etudiant] = creerContexteS4();

    $cropJson = json_encode(['x' => 45, 'y' => 20, 'width' => 300, 'height' => 200]);

    $this->actingAs($etudiant)
        ->postJson(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-images",
            [
                'image' => UploadedFile::fake()->image('photo.png', 800, 600),
                'alt' => 'Ma photo',
                'crop_data' => $cropJson,
            ],
        )
        ->assertCreated();

    $image = MuseeImage::where('projet_recherche_id', $projet->id)->latest()->first();
    expect($image->crop_data)->toMatchArray(['x' => 45, 'y' => 20]);
});

test('un non-membre ne peut pas modifier le crop_data d\'une image (403)', function () {
    Storage::fake('public');
    [$cours, $classe, $groupe, $typeProjet, , $projet] = creerContexteS4();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $image = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/test.jpg',
        'alt' => '',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 512,
    ]);

    $this->actingAs($intrus)
        ->patchJson(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-images/{$image->id}",
            ['crop_data' => ['x' => 50, 'y' => 50]],
        )
        ->assertForbidden();
});

// ─── Tâche 4.2 — Bloc Carrousel ───────────────────────────────────────────────

test('un membre peut créer un bloc carrousel', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

    $this->actingAs($etudiant)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs",
            ['type' => 'carrousel'],
        )
        ->assertRedirect();

    $bloc = MuseeBloc::where('projet_recherche_id', $projet->id)
        ->where('type', 'carrousel')
        ->first();

    expect($bloc)->not->toBeNull();
    expect($bloc->contenu)->toBe(['images' => []]);
});

test('un membre peut ajouter des images à un carrousel', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

    $image1 = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/a.jpg',
        'alt' => 'A',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 100,
    ]);
    $image2 = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/b.jpg',
        'alt' => 'B',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 100,
    ]);

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'carrousel',
        'contenu' => ['images' => []],
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            [
                'images' => [
                    ['image_id' => $image1->id, 'alt' => 'Alt A', 'legende' => 'Légende A'],
                    ['image_id' => $image2->id, 'alt' => 'Alt B', 'legende' => ''],
                ],
            ],
        )
        ->assertRedirect();

    $contenu = $bloc->fresh()->contenu;
    expect($contenu['images'])->toHaveCount(2);
    expect($contenu['images'][0]['image_id'])->toBe($image1->id);
    expect($contenu['images'][1]['image_id'])->toBe($image2->id);
});

test('le store rejette un ID image inexistant dans un carrousel', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'carrousel',
        'contenu' => ['images' => []],
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            ['images' => [['image_id' => 99999, 'alt' => '', 'legende' => '']]],
        )
        ->assertSessionHasErrors('images.0.image_id');
});

// ─── Tâche 4.3 — Image ancrée dans un bloc texte ─────────────────────────────

test('un membre peut associer une image ancrée à un bloc texte', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

    $image = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/ancre.jpg',
        'alt' => 'Ancre',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 512,
    ]);

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'texte',
        'contenu' => ['html' => '<p>Texte</p>'],
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            [
                'html' => '<p>Texte avec image</p>',
                'image_ancree' => ['image_id' => $image->id, 'position' => 'droite'],
            ],
        )
        ->assertRedirect();

    $contenu = $bloc->fresh()->contenu;
    expect($contenu['html'])->toBe('<p>Texte avec image</p>');
    expect($contenu['image_ancree']['image_id'])->toBe($image->id);
    expect($contenu['image_ancree']['position'])->toBe('droite');
});

test('la position d\'image ancrée doit être gauche ou droite', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

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
            ['html' => '', 'image_ancree' => ['image_id' => null, 'position' => 'invalide']],
        )
        ->assertSessionHasErrors('image_ancree.position');
});

test('une mise à jour de texte sans image_ancree ne touche pas le contenu existant', function () {
    [$cours, $classe, $groupe, $typeProjet, $section, $projet, , $etudiant] = creerContexteS4();

    $image = MuseeImage::create([
        'projet_recherche_id' => $projet->id,
        'path' => 'storage/musee/images/ancre2.jpg',
        'alt' => 'A2',
        'legende' => '',
        'mime_type' => 'image/jpeg',
        'taille' => 512,
    ]);

    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $projet->id,
        'section_id' => $section->id,
        'type' => 'texte',
        'contenu' => ['html' => '<p>Initial</p>', 'image_ancree' => ['image_id' => $image->id, 'position' => 'gauche']],
        'ordre' => 1,
    ]);

    // Mise à jour du HTML uniquement — pas de image_ancree dans la requête
    $this->actingAs($etudiant)
        ->patch(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/blocs/{$bloc->id}",
            ['html' => '<p>Modifié</p>'],
        )
        ->assertRedirect();

    $contenu = $bloc->fresh()->contenu;
    // HTML mis à jour, image_ancree intacte
    expect($contenu['html'])->toBe('<p>Modifié</p>');
    expect($contenu)->not->toHaveKey('image_ancree');
});

// ─── Tâche 4.4 — Position de l'image d'en-tête ───────────────────────────────

test('updateEntete accepte et persiste entete_image_position', function () {
    [$cours, $classe, $groupe, $typeProjet, , $projet, , $etudiant] = creerContexteS4();

    $this->actingAs($etudiant)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta/entete",
            [
                'entete_titre' => 'Mon titre',
                'entete_image_position' => 'top',
            ],
        )
        ->assertRedirect();

    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first();
    expect($meta->entete_image_position)->toBe('top');
});

test('updateEntete rejette une valeur de position invalide', function () {
    [$cours, $classe, $groupe, $typeProjet, , , , $etudiant] = creerContexteS4();

    $this->actingAs($etudiant)
        ->post(
            "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta/entete",
            ['entete_image_position' => 'oblique'],
        )
        ->assertSessionHasErrors('entete_image_position');
});

test('la page publique transmet entete_image_position', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours pos test',
        'code' => '330-PT',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $classe = Classe::create(['code' => 'H2025', 'cours_id' => $cours->id]);
    $groupe = Groupe::create([
        'numero' => 1,
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée pos',
        'type' => 'musee',
        'accessible' => true,
    ]);
    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first()
        ?? MuseeMeta::create(['projet_recherche_id' => $projet->id, 'slug' => 'musee-pos-test']);

    $meta->update(['slug' => 'musee-pos-test', 'entete_image_position' => 'bottom']);

    MuseePublication::create([
        'projet_recherche_id' => $projet->id,
        'est_publie' => true,
        'publie_le' => now(),
        'publie_par' => $enseignant->id,
    ]);

    $this->get('/musee/musee-pos-test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('meta.entete_image_position', 'bottom'),
        );
});
