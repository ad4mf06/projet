<?php

namespace App\Http\Controllers;

use App\Models\EpoqueHistorique;
use App\Models\GroupeVideo;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseeVue;
use App\Models\RegionAdministrative;
use App\Models\Thematique;
use App\Models\TypeProjetSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseePublicController extends Controller
{
    /**
     * Affiche la page d'accueil du musée virtuel public.
     *
     * Présente le musée et expose les 6 publications les plus récentes.
     */
    public function accueil(): Response
    {
        $base = $this->publieesMetas();

        $miseEnAvant = (clone $base)
            ->with(['projetRecherche.groupe.membres', 'epoque', 'thematique', 'regionAdministrative'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (MuseeMeta $meta) => $this->formatMeta($meta));

        return Inertia::render('Musee/Public/Accueil', [
            'miseEnAvant' => $miseEnAvant,
            'total' => (clone $base)->count(),
        ]);
    }

    /**
     * Affiche la galerie publique des musées virtuels publiés.
     *
     * Supporte le filtrage côté serveur par époque historique, région administrative
     * et thématique via les query params `epoque_id`, `region_id`, `thematique_id`.
     * Les options de filtres n'exposent que les valeurs présentes dans
     * des projets effectivement publiés.
     */
    public function explorer(Request $request): Response
    {
        $base = $this->publieesMetas();

        $musees = (clone $base)
            ->with(['projetRecherche.groupe.membres', 'epoque', 'thematique', 'regionAdministrative'])
            ->when($request->filled('epoque_id'), fn ($q) => $q->where('epoque_historique_id', $request->integer('epoque_id')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_administrative_id', $request->integer('region_id')))
            ->when($request->filled('thematique_id'), fn ($q) => $q->where('thematique_id', $request->integer('thematique_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (MuseeMeta $meta) => $this->formatMeta($meta));

        // N'exposer que les options effectivement utilisées dans des musées publiés
        $epoqueIds = (clone $base)->whereNotNull('epoque_historique_id')->pluck('epoque_historique_id');
        $regionIds = (clone $base)->whereNotNull('region_administrative_id')->pluck('region_administrative_id');
        $thematiqueIds = (clone $base)->whereNotNull('thematique_id')->pluck('thematique_id');

        return Inertia::render('Musee/Public/Explorer', [
            'musees' => $musees,
            'filtres' => [
                'epoque_id' => $request->filled('epoque_id') ? $request->integer('epoque_id') : null,
                'region_id' => $request->filled('region_id') ? $request->integer('region_id') : null,
                'thematique_id' => $request->filled('thematique_id') ? $request->integer('thematique_id') : null,
            ],
            'options' => [
                'epoques' => EpoqueHistorique::whereIn('id', $epoqueIds)->orderBy('ordre')->get(['id', 'nom']),
                'regions' => RegionAdministrative::whereIn('id', $regionIds)->orderBy('ordre')->get(['id', 'nom']),
                'thematiques' => Thematique::whereIn('id', $thematiqueIds)->orderBy('nom')->get(['id', 'nom']),
            ],
        ]);
    }

    /**
     * Affiche la page publique d'invitation à contribuer en tant que témoin.
     */
    public function contribuer(): Response
    {
        return Inertia::render('Musee/Public/Contribuer');
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────────

    /**
     * Retourne la requête de base pour les musées dont la publication est active.
     *
     * Utilisé par accueil() et explorer() pour éviter la répétition du whereHas.
     *
     * @return Builder<MuseeMeta>
     */
    private function publieesMetas(): Builder
    {
        return MuseeMeta::query()
            ->whereHas('projetRecherche.museePublication', fn ($q) => $q->where('est_publie', true));
    }

    /**
     * Sérialise un MuseeMeta en tableau pour les pages publiques (accueil, explorer).
     *
     * @return array<string, mixed>
     */
    private function formatMeta(MuseeMeta $meta): array
    {
        return [
            'slug' => $meta->slug,
            'titre' => $meta->entete_titre,
            'intro_texte' => $meta->intro_texte,
            'image_path' => $meta->intro_image_path ?? $meta->entete_image_path,
            'periode' => $meta->epoque?->only('id', 'nom'),
            'thematique' => $meta->thematique?->only('id', 'nom'),
            'region' => $meta->regionAdministrative?->only('id', 'nom'),
            'membres' => $meta->projetRecherche->groupe->membres
                ->map(fn ($m) => $m->prenom.' '.$m->nom)
                ->all(),
        ];
    }

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
                'epoque',
                'thematique',
                'regionAdministrative',
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

        $blocsParSection = $projet->museeBlocs()
            ->with('videoSegments')
            ->orderBy('ordre')
            ->get()
            ->groupBy('section_id');

        $sections = $sectionsTypeProjet->map(fn ($section) => [
            'id' => $section->id,
            'label' => $section->label,
            'ordre' => $section->ordre,
            'blocs' => ($blocsParSection->get($section->id) ?? collect())->map(fn ($bloc) => [
                'id' => $bloc->id,
                'type' => $bloc->type,
                'contenu' => $bloc->contenu,
                'ordre' => $bloc->ordre,
                'segments' => $bloc->type === MuseeBloc::TYPE_VIDEO
                    ? $bloc->videoSegments->map->only('id', 'section_id', 'debut_secondes', 'fin_secondes', 'label')->toArray()
                    : [],
            ])->values()->toArray(),
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

        // Index des sections pour la navigation segments → section_id → label
        $sectionsIndex = $sectionsTypeProjet->mapWithKeys(fn ($s) => [$s->id => $s->label]);

        return Inertia::render('Musee/Public/Show', [
            'sectionsIndex' => $sectionsIndex,
            'meta' => [
                'slug' => $meta->slug,
                'intro_texte' => $meta->intro_texte,
                'intro_image_path' => $meta->intro_image_path,
                'entete_titre' => $meta->entete_titre,
                'entete_sous_titre' => $meta->entete_sous_titre,
                'entete_overlay_couleur' => $meta->entete_overlay_couleur,
                'entete_image_position' => $meta->entete_image_position ?? 'center',
                'entete_image_path' => $meta->entete_image_path,
                'periode' => $meta->epoque?->only('id', 'nom'),
                'thematique' => $meta->thematique?->only('id', 'nom'),
                'region' => $meta->regionAdministrative?->only('id', 'nom'),
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

    /**
     * Diffuse le fichier vidéo d'un bloc musée publié, sans authentification.
     *
     * Le slug du musée est vérifié — seules les vidéos dont le musée parent
     * est publié (`est_publie = true`) sont servies. Cela évite d'exposer
     * des vidéos de musées non publiés par simple guessing d'ID de bloc.
     *
     * @throws HttpException
     */
    public function streamVideo(string $slug, MuseeBloc $bloc): BinaryFileResponse
    {
        $meta = MuseeMeta::where('slug', $slug)
            ->with(['projetRecherche.museePublication'])
            ->firstOrFail();

        $projet = $meta->projetRecherche;

        abort_unless($projet->museePublication?->est_publie, 404);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->type !== MuseeBloc::TYPE_VIDEO, 404);
        abort_unless(($bloc->contenu['source'] ?? '') === 'upload', 404);

        $videoId = $bloc->contenu['groupe_video_id'] ?? null;
        abort_if($videoId === null, 404);

        // Charger la GroupeVideo et vérifier qu'elle appartient au groupe du projet
        $video = GroupeVideo::findOrFail($videoId);
        abort_if($video->groupe_id !== $projet->groupe_id, 403);

        $path = $video->absolutePath();
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }
}
