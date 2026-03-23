<?php

namespace App\Http\Controllers;

use App\Actions\ExportProjetPdf;
use App\Actions\ExportProjetWord;
use App\Http\Requests\UpsertProjetCommentaireRequest;
use App\Http\Requests\UpsertProjetNoteRequest;
use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetCommentaire;
use App\Models\ProjetConclusion;
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

        // Créer le projet partagé s'il n'existe pas encore (accès à l'éditeur implique volonté de créer)
        $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id]);

        // Précharger en une seule requête chacune des relations — évite le N+1
        $projet->load(['conclusions', 'commentaires', 'notes']);

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
        $notesParEtudiant = $projet->notes
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(fn ($notes) => $notes->keyBy('critere')->map(fn (ProjetNote $n) => $n->note));

        // Note finale calculée par étudiant
        $noteFinaleParEtudiant = $groupe->membres->mapWithKeys(
            fn (User $membre) => [$membre->id => ProjetNote::noteFinale($projet, $membre)]
        );

        return Inertia::render('Projets/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'enseignant' => $groupe->classe->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet,
            'conclusions' => $conclusions,
            'peutEditer' => $groupe->membres()->where('users.id', $user->id)->exists(),
            'estEnseignant' => $groupe->classe->enseignant_id === $user->id,
            'commentaires' => $commentaires,
            'notesParEtudiant' => $notesParEtudiant,
            'noteFinaleParEtudiant' => $noteFinaleParEtudiant,
            'criteres' => ProjetNote::CRITERES,
            'criteresSections' => ProjetNote::CRITERES_PAR_SECTION,
        ]);
    }

    /**
     * Met à jour le contenu partagé du projet (titre, dev_count, introduction, développements).
     *
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
            'dev_count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'introduction_amener' => ['nullable', 'string'],
            'introduction_poser' => ['nullable', 'string'],
            'introduction_diviser' => ['nullable', 'string'],
            'dev_1_titre' => ['nullable', 'string', 'max:500'],
            'dev_1_contenu' => ['nullable', 'string'],
            'dev_2_titre' => ['nullable', 'string', 'max:500'],
            'dev_2_contenu' => ['nullable', 'string'],
            'dev_3_titre' => ['nullable', 'string', 'max:500'],
            'dev_3_contenu' => ['nullable', 'string'],
            'dev_4_titre' => ['nullable', 'string', 'max:500'],
            'dev_4_contenu' => ['nullable', 'string'],
            'dev_5_titre' => ['nullable', 'string', 'max:500'],
            'dev_5_contenu' => ['nullable', 'string'],
        ]);

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
        $projet->load('conclusions.etudiant');

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
        $projet->load('conclusions.etudiant');

        return (new ExportProjetWord)->execute($projet, $groupe);
    }
}
