<?php

namespace App\Http\Controllers;

use App\Actions\ExportProjetPdf;
use App\Actions\ExportProjetWord;
use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
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
     * Utilise un eager load des conclusions pour éviter le N+1.
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

        // Précharger les conclusions en une seule requête — évite le N+1 dans la boucle membres
        $projet->load('conclusions');
        $conclusionsParMembre = $projet->conclusions->keyBy('user_id');

        $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
            $conclusion = $conclusionsParMembre->get($membre->id);

            return [
                'etudiant' => $membre->only('id', 'prenom', 'nom'),
                'contenu' => $conclusion?->contenu,
            ];
        });

        return Inertia::render('Projets/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'enseignant' => $groupe->classe->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet,
            'conclusions' => $conclusions,
            'peutEditer' => $groupe->membres()->where('users.id', $user->id)->exists(),
            'estEnseignant' => $groupe->classe->enseignant_id === $user->id,
        ]);
    }

    /**
     * Met à jour le contenu partagé du projet (titre, introduction, développements).
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
