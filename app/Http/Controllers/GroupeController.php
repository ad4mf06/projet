<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeNote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GroupeController extends Controller
{
    /**
     * Affiche la page de gestion des groupes d'un étudiant dans une classe.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit
     */
    public function index(Classe $classe): Response
    {
        $user = auth()->user();

        // Vérification d'inscription à la classe — s'applique au modèle Classe, pas Groupe
        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->with(['membres', 'thematiques', 'createur'])
            ->first();

        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'prenom', 'nom']);

        $thematiques = $classe->enseignant->thematiques()->get(['id', 'nom', 'periode_historique']);

        $documents = $classe->documents()->get();

        return Inertia::render('Classes/Groupes', [
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'monGroupe' => $monGroupe,
            'autresEtudiants' => $autresEtudiants,
            'thematiques' => $thematiques,
            'documents' => $documents,
        ]);
    }

    /**
     * Crée un nouveau groupe et associe les membres et thématiques dans une transaction atomique.
     *
     * Les IDs des membres sont filtrés pour ne garder que les étudiants réellement
     * inscrits dans la classe. Les thématiques sont filtrées pour n'accepter que
     * celles appartenant à l'enseignant de la classe. Sans transaction, une erreur
     * sur attach() laisserait un groupe orphelin en base de données.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit ou déjà dans un groupe
     */
    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $user = auth()->user();

        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $dejaDansGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if ($dejaDansGroupe) {
            return back()->withErrors(['general' => __('groupe.already_member')]);
        }

        $validated = $request->validate([
            'membres' => ['array'],
            'membres.*' => ['integer', 'exists:users,id'],
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        // Garder seulement les membres réellement inscrits dans cette classe
        $membresInscrits = $classe->etudiants()
            ->whereIn('users.id', $validated['membres'] ?? [])
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Garder seulement les thématiques appartenant à l'enseignant de la classe
        $thematiquesValides = $classe->enseignant
            ->thematiques()
            ->whereIn('id', $validated['thematiques'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        DB::transaction(function () use ($user, $classe, $membresInscrits, $thematiquesValides) {
            $groupe = Groupe::create([
                'classe_id' => $classe->id,
                'created_by' => $user->id,
            ]);

            // Le créateur est toujours inclus même s'il ne s'est pas sélectionné lui-même
            $membres = array_unique(array_merge([(int) $user->id], $membresInscrits));
            $groupe->membres()->attach($membres);

            if (! empty($thematiquesValides)) {
                $groupe->thematiques()->attach($thematiquesValides);
            }
        });

        return back()->with('success', __('groupe.created'));
    }

    /**
     * Affiche le détail d'un groupe avec ses membres, thématiques, notes et médias.
     *
     * Accessible aux membres du groupe, à l'enseignant de la classe et aux admins.
     *
     * @throws AuthorizationException
     */
    public function show(Classe $classe, Groupe $groupe): Response
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('view', $groupe);

        $user = auth()->user();

        $estMembre = $groupe->membres()->where('users.id', $user->id)->exists();
        $estEnseignant = $groupe->classe->enseignant_id === $user->id;

        $groupe->load([
            'membres',
            'thematiques',
            'notes.auteur',
            'createur',
            'medias.auteur',
        ]);

        $thematiquesDispo = $groupe->classe->enseignant
            ->thematiques()
            ->get(['id', 'nom', 'periode_historique']);

        $membreIds = $groupe->membres->pluck('id');
        $etudiantsDispo = $groupe->classe->etudiants()
            ->whereNotIn('users.id', $membreIds)
            ->get(['users.id', 'prenom', 'nom']);

        return Inertia::render('Groupes/Show', [
            'groupe' => $groupe,
            'estMembre' => $estMembre,
            'estEnseignant' => $estEnseignant,
            'estCreateur' => $groupe->created_by === $user->id,
            'thematiquesDispo' => $thematiquesDispo,
            'etudiantsDispo' => $etudiantsDispo,
        ]);
    }

    /**
     * Ajoute ou retire des membres du groupe (créateur uniquement).
     *
     * Le créateur ne peut pas se retirer lui-même. Seuls les étudiants inscrits
     * dans la classe peuvent être ajoutés. L'opération est atomique.
     *
     * @throws AuthorizationException
     */
    public function updateMembres(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('manageMembers', $groupe);

        $validated = $request->validate([
            'ajouter' => ['array'],
            'ajouter.*' => ['integer', 'exists:users,id'],
            'retirer' => ['array'],
            'retirer.*' => ['integer', 'exists:users,id'],
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($validated, $user, $classe, $groupe) {
            if (! empty($validated['ajouter'])) {
                $aAjouter = $classe->etudiants()
                    ->whereIn('users.id', $validated['ajouter'])
                    ->pluck('users.id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                if (! empty($aAjouter)) {
                    $groupe->membres()->syncWithoutDetaching($aAjouter);
                }
            }

            // Le créateur ne peut jamais être retiré de son propre groupe
            $aRetirer = array_diff(
                array_map('intval', $validated['retirer'] ?? []),
                [(int) $user->id]
            );

            if (! empty($aRetirer)) {
                $groupe->membres()->detach($aRetirer);
            }
        });

        return back()->with('success', __('groupe.members_updated'));
    }

    /**
     * Remplace complètement les thématiques du groupe (sync).
     *
     * Accessible à tout membre du groupe. Maximum 3 thématiques. Seules les
     * thématiques appartenant à l'enseignant de la classe sont acceptées.
     *
     * @throws AuthorizationException
     */
    public function updateThematiques(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe);

        $validated = $request->validate([
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        // Filtrer aux thématiques de l'enseignant de la classe uniquement
        $thematiquesValides = $groupe->classe->enseignant
            ->thematiques()
            ->whereIn('id', $validated['thematiques'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        DB::transaction(function () use ($thematiquesValides, $groupe) {
            $groupe->thematiques()->sync($thematiquesValides);
        });

        return back()->with('success', __('groupe.thematiques_updated'));
    }

    /**
     * Ajoute une note collaborative au groupe.
     *
     * Accessible aux membres du groupe uniquement.
     *
     * @throws AuthorizationException
     */
    public function storeNote(Request $request, Groupe $groupe): RedirectResponse
    {
        $groupe->load('classe');
        $this->authorize('addNote', $groupe);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        GroupeNote::create([
            'groupe_id' => $groupe->id,
            'user_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', __('groupe.note_created'));
    }

    /**
     * Supprime une note du groupe.
     *
     * Seul l'auteur de la note peut la supprimer.
     *
     * @throws HttpException si l'utilisateur n'est pas l'auteur
     */
    public function destroyNote(Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        abort_if($note->user_id !== auth()->id(), 403);

        $note->delete();

        return back()->with('success', __('groupe.note_deleted'));
    }

    /**
     * Supprime un groupe entier.
     *
     * Seul le créateur peut supprimer. La suppression cascade sur les notes,
     * médias et les entrées des tables pivot via les FK avec cascadeOnDelete.
     *
     * @throws AuthorizationException
     */
    public function destroy(Classe $classe, Groupe $groupe): RedirectResponse
    {
        $groupe->load('classe');
        $this->authorize('delete', $groupe);

        $groupe->delete();

        return redirect()->route('groupes.index', $classe)->with('success', __('groupe.deleted'));
    }
}
