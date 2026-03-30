<?php

namespace App\Http\Controllers;

use App\Actions\ExportProjetPdf;
use App\Actions\ExportProjetWord;
use App\Http\Requests\UpsertProjetCommentaireRequest;
use App\Http\Requests\UpsertProjetNoteRequest;
use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetCommentaire;
use App\Models\ProjetConclusion;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjetRechercheController extends Controller
{
    /** Pattern regex validant les noms de champs annotables (introductions ou développement_{id}). */
    private const CHAMP_ANNOTABLE_REGEX = '/^(introduction_amener|introduction_poser|introduction_diviser|developpement_\d+)$/';

    /**
     * Affiche le projet de recherche du groupe avec l'avancement de chaque conclusion.
     *
     * Utilise un eager load des conclusions pour éviter le N+1 (une requête par membre).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function index(Classe $classe, Groupe $groupe): Response
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'classe']);
        $this->authorize('view', $groupe);

        $user = auth()->user();

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->first();

        // Précharger les conclusions en une seule requête — évite le N+1 dans la boucle membres
        if ($projet) {
            $projet->load('conclusions');
        }
        $conclusionsParMembre = $projet ? $projet->conclusions->keyBy('user_id') : collect();

        $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
            $conclusion = $conclusionsParMembre->get($membre->id);

            return [
                'etudiant' => $membre->only('id', 'prenom', 'nom'),
                'a_redige' => $conclusion !== null && trim(strip_tags((string) ($conclusion->contenu ?? ''))) !== '',
            ];
        });

        return Inertia::render('Projets/Index', [
            'groupe' => $groupe->only('id', 'nom', 'classe_id'),
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'projet' => $projet
                ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet, 'completion' => $projet->completion()]
                : null,
            'conclusions' => $conclusions,
            'estEnseignant' => $groupe->classe->enseignant_id === $user->id,
        ]);
    }

    /**
     * Affiche le projet partagé avec l'éditeur de contenu et les conclusions individuelles.
     *
     * Crée le projet s'il n'existe pas encore (premier accès à l'éditeur).
     * Utilise un eager load des conclusions, commentaires et notes pour éviter le N+1.
     * Filtre les annotations de type "correction" pour les étudiants si correction_visible = false.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function show(Classe $classe, Groupe $groupe): Response
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'thematiques', 'classe.enseignant']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $groupe->classe->enseignant_id === $user->id;

        // Créer le projet partagé s'il n'existe pas encore (accès à l'éditeur implique volonté de créer)
        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        // Précharger en une seule requête chacune des relations — évite le N+1
        $projet->load(['conclusions', 'commentaires', 'notes', 'annotations', 'developpements']);

        $conclusionsParMembre = $projet->conclusions->keyBy('user_id');

        $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
            $conclusion = $conclusionsParMembre->get($membre->id);

            return [
                'etudiant' => $membre->only('id', 'prenom', 'nom'),
                'contenu' => $conclusion?->contenu,
            ];
        });

        // Commentaires indexés par champ pour un accès O(1) côté Vue
        $commentaires = $projet->commentaires->keyBy('champ')->map(fn (ProjetCommentaire $c) => [
            'id' => $c->id,
            'contenu' => $c->contenu,
        ]);

        // Notes par étudiant : ['user_id' => ['critere' => note]]
        // Masquées pour les étudiants tant que l'enseignant n'a pas publié les corrections
        $notesParEtudiant = ($estEnseignant || $projet->correction_visible)
            ? $projet->notes
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->map(fn ($notes) => $notes->keyBy('critere')->map(fn (ProjetNote $n) => $n->note))
            : $groupe->membres->mapWithKeys(fn (User $membre) => [$membre->id => []]);

        // Note finale calculée par étudiant — masquée si corrections non publiées
        $noteFinaleParEtudiant = ($estEnseignant || $projet->correction_visible)
            ? $groupe->membres->mapWithKeys(
                fn (User $membre) => [$membre->id => ProjetNote::noteFinale($projet, $membre)]
            )
            : $groupe->membres->mapWithKeys(fn (User $membre) => [$membre->id => null]);

        // Pour les étudiants, masquer les corrections si correction_visible = false
        $annotationsFiltrees = $estEnseignant
            ? $projet->annotations
            : $projet->annotations->when(
                ! $projet->correction_visible,
                fn ($coll) => $coll->where('type', 'commentaire')
            );

        // Développements indexés par id pour accès O(1) lors du tri des annotations
        $developpementsParId = $projet->developpements->keyBy('id');

        // Annotations inline indexées par champ, triées par position dans le texte.
        // Le tri utilise strpos sur le HTML du champ pour garantir l'ordre visuel,
        // indépendamment de l'ordre d'insertion en base (évite le désordre après reconnexion).
        $annotationsParChamp = $annotationsFiltrees
            ->groupBy('champ')
            ->map(function ($annotations, string $champ) use ($projet, $developpementsParId) {
                // Récupérer le HTML du champ pour déterminer la position de chaque marque
                if (str_starts_with($champ, 'developpement_')) {
                    $devId = (int) mb_substr($champ, mb_strlen('developpement_'));
                    $html = $developpementsParId->get($devId)?->contenu ?? '';
                } else {
                    $html = $projet->$champ ?? '';
                }

                return $annotations
                    ->sortBy(function (ProjetAnnotation $a) use ($html): int {
                        $pos = strpos($html, 'data-comment-id="'.$a->commentaire_id.'"');

                        // Les annotations orphelines (marque absente du HTML) vont en fin de liste
                        return $pos === false ? PHP_INT_MAX : $pos;
                    })
                    ->map(fn (ProjetAnnotation $a) => [
                        'id' => $a->id,
                        'commentaire_id' => $a->commentaire_id,
                        'contenu' => $a->contenu,
                        'type' => $a->type,
                        'user_id' => $a->user_id,
                    ])
                    ->values();
            });

        $estMembre = ! $estEnseignant && $groupe->membres()->where('users.id', $user->id)->exists();

        // Condition commune : membre + non verrouillé + remise encore possible
        $peutAgir = $estMembre && ! $projet->verrouille && $projet->peutEtreRemis();

        return Inertia::render('Projets/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'enseignant' => $groupe->classe->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet,
            'developpements' => $projet->developpements->map->only('id', 'ordre', 'titre', 'contenu')->values(),
            'conclusions' => $conclusions,
            'peutEditer' => $peutAgir,
            'estEnseignant' => $estEnseignant,
            'correctionVisible' => (bool) $projet->correction_visible,
            'verrouille' => (bool) $projet->verrouille,
            'dateRemise' => $projet->date_remise?->toIso8601String(),
            'remisLe' => $projet->remis_le?->toIso8601String(),
            'remisesMultiples' => (bool) $projet->remises_multiples,
            'peutRemettre' => $peutAgir,
            'commentaires' => $commentaires,
            'notesParEtudiant' => $notesParEtudiant,
            'noteFinaleParEtudiant' => $noteFinaleParEtudiant,
            'criteres' => ProjetNote::CRITERES,
            'criteresSections' => ProjetNote::CRITERES_PAR_SECTION,
            'annotationsParChamp' => $annotationsParChamp,
        ]);
    }

    /**
     * Met à jour le contenu partagé du projet (titre, sections d'introduction).
     *
     * Les paragraphes de développement sont gérés par leurs propres routes.
     * Tout membre du groupe peut modifier ce contenu.
     *
     * @throws HttpException
     */
    public function update(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe); // membre du groupe uniquement

        $validated = $request->validate([
            'titre_projet' => ['nullable', 'string', 'max:500'],
            'introduction_amener' => ['nullable', 'string'],
            'introduction_poser' => ['nullable', 'string'],
            'introduction_diviser' => ['nullable', 'string'],
        ]);

        $existant = ProjetRecherche::where('groupe_id', $groupe->id)->first();
        abort_if($existant?->verrouille, 403, 'Ce document est verrouillé.');
        abort_if($existant !== null && ! $existant->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $projet = ProjetRecherche::updateOrCreate(
            ['groupe_id' => $groupe->id],
            $validated,
        );

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Ajoute un nouveau paragraphe de développement à la fin de la liste.
     *
     * @throws HttpException
     */
    public function storeDeveloppement(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserMembreGroupe($classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $ordre = ($projet->developpements()->max('ordre') ?? 0) + 1;

        $dev = ProjetDeveloppement::create([
            'projet_id' => $projet->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

        return response()->json([
            'message' => 'created',
            'developpement' => $dev->only('id', 'ordre', 'titre', 'contenu'),
            'completion' => $projet->completion(),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de développement.
     *
     * @throws HttpException
     */
    public function updateDeveloppement(Request $request, Classe $classe, Groupe $groupe, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->autoriserMembreGroupe($classe, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        abort_if($developpement->projet_id !== $projet->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'titre' => ['nullable', 'string', 'max:500'],
            'contenu' => ['nullable', 'string'],
        ]);

        $developpement->update($validated);

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Supprime un paragraphe de développement et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     */
    public function destroyDeveloppement(Classe $classe, Groupe $groupe, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->autoriserMembreGroupe($classe, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        abort_if($developpement->projet_id !== $projet->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');
        abort_if($projet->developpements()->count() <= 1, 422, 'Le projet doit conserver au moins un paragraphe.');

        $developpement->delete();

        // Renuméroter les paragraphes restants pour éviter les trous
        $projet->developpements()->orderBy('ordre')->each(
            function (ProjetDeveloppement $dev, int $index): void {
                $dev->update(['ordre' => $index + 1]);
            }
        );

        return response()->json([
            'message' => 'deleted',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes de développement d'un projet.
     *
     * @throws HttpException
     */
    public function reorderDeveloppements(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserMembreGroupe($classe, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'exists:projet_developpements,id'],
        ]);

        foreach ($validated['ordre'] as $index => $id) {
            // Le filtre projet_id empêche de réordonner les paragraphes d'un autre projet
            ProjetDeveloppement::where('id', $id)
                ->where('projet_id', $projet->id)
                ->update(['ordre' => $index + 1]);
        }

        return response()->json(['message' => 'reordered']);
    }

    /**
     * Sauvegarde la conclusion individuelle de l'étudiant authentifié.
     *
     * @throws HttpException
     */
    public function updateConclusion(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe); // membre du groupe uniquement

        $validated = $request->validate([
            'contenu' => ['nullable', 'string'],
        ]);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

        ProjetConclusion::updateOrCreate(
            ['projet_id' => $projet->id, 'user_id' => auth()->id()],
            ['contenu' => $validated['contenu']],
        );

        return response()->json(['message' => 'saved']);
    }

    /**
     * Crée ou met à jour le commentaire de l'enseignant pour un champ donné.
     *
     * Seul l'enseignant de la classe peut commenter.
     *
     * @throws HttpException
     */
    public function upsertCommentaire(UpsertProjetCommentaireRequest $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        $commentaire = ProjetCommentaire::updateOrCreate(
            ['projet_id' => $projet->id, 'champ' => $request->validated('champ')],
            ['contenu' => $request->validated('contenu'), 'created_by' => auth()->id()],
        );

        return response()->json([
            'message' => 'saved',
            'id' => $commentaire->id,
            'contenu' => $commentaire->contenu,
        ]);
    }

    /**
     * Supprime un commentaire de l'enseignant.
     *
     * @throws HttpException
     */
    public function destroyCommentaire(Classe $classe, Groupe $groupe, ProjetCommentaire $commentaire): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        // Vérifier que le commentaire appartient bien au projet de ce groupe — évite l'IDOR
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        abort_if($commentaire->projet_id !== $projet->id, 404);

        $commentaire->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Crée ou met à jour la note d'un critère de la grille de correction.
     *
     * Seul l'enseignant de la classe peut noter.
     *
     * @throws HttpException
     */
    public function upsertNote(UpsertProjetNoteRequest $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        // S'assurer que l'étudiant noté est bien membre de ce groupe — évite de noter un élève d'un autre groupe
        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', $request->validated('user_id')),
            422,
            'Cet étudiant n\'est pas membre de ce groupe.',
        );

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        ProjetNote::updateOrCreate(
            [
                'projet_id' => $projet->id,
                'user_id' => $request->validated('user_id'),
                'critere' => $request->validated('critere'),
            ],
            ['note' => $request->validated('note')],
        );

        // Recharger les notes pour recalculer par étudiant
        $projet->load('notes');
        $groupe->load('membres');

        $noteFinaleParEtudiant = $groupe->membres->mapWithKeys(
            fn (User $membre) => [$membre->id => ProjetNote::noteFinale($projet, $membre)]
        );

        return response()->json([
            'message' => 'saved',
            'noteFinaleParEtudiant' => $noteFinaleParEtudiant,
        ]);
    }

    /**
     * Crée ou met à jour une annotation inline sur un champ du projet.
     *
     * Met à jour simultanément le HTML du champ (avec la marque CommentMark)
     * et persiste le texte de l'annotation via un upsert sur le commentaire_id.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant de la classe
     */
    public function upsertAnnotation(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'html' => ['required', 'string'],
            'type' => ['sometimes', 'string', 'in:commentaire,correction'],
        ]);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        // Mettre à jour le HTML du champ correspondant (projet ou paragraphe de développement)
        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);

        // Upsert de l'annotation par commentaire_id (clé naturelle de la marque TipTap)
        $annotation = ProjetAnnotation::updateOrCreate(
            ['projet_id' => $projet->id, 'commentaire_id' => $validated['commentaire_id']],
            [
                'champ' => $validated['champ'],
                'contenu' => $validated['contenu'],
                'type' => $validated['type'] ?? 'commentaire',
                'user_id' => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'saved',
            'id' => $annotation->id,
            'commentaire_id' => $annotation->commentaire_id,
            'contenu' => $annotation->contenu,
            'type' => $annotation->type,
            'user_id' => $annotation->user_id,
        ]);
    }

    /**
     * Supprime une annotation inline et met à jour le HTML du champ pour retirer la marque.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant ou si l'annotation ne correspond pas
     */
    public function destroyAnnotation(Request $request, Classe $classe, Groupe $groupe, ProjetAnnotation $annotation): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        abort_if($annotation->projet_id !== $projet->id, 404);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'html' => ['required', 'string'],
        ]);

        // Mettre à jour le HTML sans la marque supprimée
        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);

        $annotation->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Enregistre la remise du travail par l'équipe d'étudiants.
     *
     * Refuse si le document est verrouillé ou si une remise existe déjà
     * sans que les remises multiples soient activées.
     *
     * @throws HttpException
     */
    public function remettreTravail(Classe $classe, Groupe $groupe): JsonResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->loadMissing('classe', 'membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_unless($projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis et les remises multiples ne sont pas autorisées.');

        $projet->update(['remis_le' => now()]);

        return response()->json([
            'message' => 'remis',
            'remis_le' => $projet->remis_le->toIso8601String(),
        ]);
    }

    /**
     * Met à jour les paramètres de remise configurés par l'enseignant
     * (date limite, remises multiples).
     *
     * @throws HttpException
     */
    public function updateParametresRemise(Request $request, Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $validated = $request->validate([
            'date_remise' => ['nullable', 'date'],
            'remises_multiples' => ['boolean'],
        ]);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);
        $projet->update($validated);

        return response()->json([
            'message' => 'saved',
            'date_remise' => $projet->date_remise?->toIso8601String(),
            'remises_multiples' => $projet->remises_multiples,
        ]);
    }

    /**
     * Active ou désactive la visibilité des corrections pour les étudiants.
     *
     * @throws HttpException
     */
    public function toggleCorrectionVisible(Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);
        $projet->update(['correction_visible' => ! $projet->correction_visible]);

        return response()->json([
            'message' => 'toggled',
            'correction_visible' => (bool) $projet->correction_visible,
        ]);
    }

    /**
     * Verrouille ou déverrouille le document pour l'édition par les étudiants.
     *
     * @throws HttpException
     */
    public function toggleVerrouille(Classe $classe, Groupe $groupe): JsonResponse
    {
        $this->autoriserEnseignant($classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);
        $projet->update(['verrouille' => ! $projet->verrouille]);

        return response()->json([
            'message' => 'toggled',
            'verrouille' => (bool) $projet->verrouille,
        ]);
    }

    /**
     * Vérifie que le groupe appartient à la classe et autorise l'action manageThematiques.
     *
     * Factorise les 3 lignes de guard communes aux 4 méthodes *Developpement.
     *
     * @throws HttpException
     */
    private function autoriserMembreGroupe(Classe $classe, Groupe $groupe): void
    {
        abort_if($groupe->classe_id !== $classe->id, 404);
        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe);
    }

    /**
     * Lève une exception si le groupe n'appartient pas à la classe
     * ou si l'utilisateur authentifié n'est pas l'enseignant de cette classe.
     *
     * @throws HttpException
     */
    private function autoriserEnseignant(Classe $classe, Groupe $groupe): void
    {
        abort_if($groupe->classe_id !== $classe->id, 404);
        $groupe->loadMissing('classe');
        abort_unless($groupe->classe->enseignant_id === auth()->id(), 403);
    }

    /**
     * Met à jour le contenu HTML d'un champ annotable.
     *
     * Si le champ est de la forme "developpement_{id}", met à jour le contenu
     * du paragraphe correspondant. Sinon, met à jour la colonne du projet directement.
     *
     * @throws HttpException si le paragraphe n'appartient pas au projet
     */
    private function mettreAJourChampHtml(ProjetRecherche $projet, string $champ, string $html): void
    {
        if (str_starts_with($champ, 'developpement_')) {
            $devId = (int) mb_substr($champ, mb_strlen('developpement_'));
            $dev = ProjetDeveloppement::where('id', $devId)
                ->where('projet_id', $projet->id)
                ->firstOrFail();
            $dev->update(['contenu' => $html]);
        } else {
            $projet->update([$champ => $html]);
        }
    }

    /**
     * Génère et retourne le projet de groupe en PDF.
     *
     * Retourne 404 si aucun projet n'a encore été créé (ne crée pas de projet vide).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function exportPdf(Classe $classe, Groupe $groupe): HttpResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'thematiques', 'classe.enseignant']);
        $this->authorize('view', $groupe);

        // firstOrFail : un export sur un projet inexistant doit retourner 404, pas créer un projet vide
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        $projet->load(['conclusions.etudiant', 'developpements']);

        return (new ExportProjetPdf)->execute($projet, $groupe);
    }

    /**
     * Génère et retourne le projet de groupe en Word (.docx).
     *
     * Retourne 404 si aucun projet n'a encore été créé (ne crée pas de projet vide).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function exportWord(Classe $classe, Groupe $groupe): StreamedResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'thematiques', 'classe.enseignant']);
        $this->authorize('view', $groupe);

        // firstOrFail : un export sur un projet inexistant doit retourner 404, pas créer un projet vide
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)->firstOrFail();
        $projet->load(['conclusions.etudiant', 'developpements']);

        return (new ExportProjetWord)->execute($projet, $groupe);
    }
}
