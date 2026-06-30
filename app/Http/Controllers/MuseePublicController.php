<?php

namespace App\Http\Controllers;

use App\Models\MuseeMeta;
use App\Models\MuseeVue;
use App\Models\TypeProjetSection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseePublicController extends Controller
{
    /**
     * Affiche la page publique du musée virtuel identifié par son slug.
     *
     * Enregistre une vue unique par IP/24h, charge le template visuel (CSS vars),
     * les sections avec leurs blocs, et la bibliothèque d'images.
     *
     * @throws HttpException
     */
    public function show(Request $request, string $slug): Response
    {
        $meta = MuseeMeta::where('slug', $slug)
            ->with([
                'projetRecherche.typeProjet.museeTemplate',
                'projetRecherche.groupe.membres',
                'projetRecherche.museePublication',
                'projetRecherche.museeImages',
                'periode',
                'thematique',
                'region',
            ])
            ->firstOrFail();

        $projet = $meta->projetRecherche;

        // Seuls les musées explicitement publiés sont accessibles au public
        abort_unless($projet->museePublication?->est_publie, 404);

        // Enregistrer la visite — dédupliquée par IP/24h pour éviter le bourrage
        MuseeVue::enregistrer($projet->id, $request->ip());

        $typeProjet = $projet->typeProjet;
        $template = $typeProjet->museeTemplate;

        // Sections définies par l'enseignant, avec leurs blocs groupés
        $sectionsTypeProjet = TypeProjetSection::where('type_projet_id', $typeProjet->id)
            ->orderBy('ordre')
            ->get();

        $blocsParSection = $projet->museeBlocs()->orderBy('ordre')->get()->groupBy('section_id');

        $sections = $sectionsTypeProjet->map(fn ($section) => [
            'id' => $section->id,
            'label' => $section->label,
            'ordre' => $section->ordre,
            'blocs' => ($blocsParSection->get($section->id) ?? collect())->values()->toArray(),
        ]);

        // Bibliothèque d'images pour résoudre les image_id dans les blocs image/carrousel
        $images = $projet->museeImages->map(fn ($img) => [
            'id' => $img->id,
            'url' => $img->url,
            'alt' => $img->alt,
            'legende' => $img->legende,
            'crop_data' => $img->crop_data,
        ]);

        $membres = $projet->groupe->membres->map(fn ($m) => [
            'id' => $m->id,
            'nom' => $m->prenom.' '.$m->nom,
        ]);

        return Inertia::render('Musee/Public/Show', [
            'meta' => [
                'slug' => $meta->slug,
                'intro_texte' => $meta->intro_texte,
                'intro_image_path' => $meta->intro_image_path,
                'entete_titre' => $meta->entete_titre,
                'entete_sous_titre' => $meta->entete_sous_titre,
                'entete_overlay_couleur' => $meta->entete_overlay_couleur,
                'entete_image_position' => $meta->entete_image_position ?? 'center',
                'entete_image_path' => $meta->entete_image_path,
                'periode' => $meta->periode?->only('id', 'nom'),
                'thematique' => $meta->thematique?->only('id', 'nom'),
                'region' => $meta->region?->only('id', 'nom'),
            ],
            'sections' => $sections,
            'images' => $images,
            'membres' => $membres,
            'cssVars' => $template?->toCssVariables() ?? [],
            'theme' => $template?->theme,
            'palette' => $template?->palette,
            'nbVues' => $projet->museeVues()->count(),
            'typeProjet' => $typeProjet->only('id', 'nom'),
        ]);
    }
}
