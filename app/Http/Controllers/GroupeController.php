<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupeController extends Controller
{
    public function index(Classe $classe): Response
    {
        $user = auth()->user();

        // Vérifier que l'étudiant est inscrit dans la classe
        if (! $classe->etudiants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        // Groupe de l'étudiant dans cette classe (s'il en a un)
        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->with(['membres', 'thematiques', 'createur'])
            ->first();

        // Autres étudiants de la classe (pour le formulaire de création)
        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'prenom', 'nom']);

        // Thématiques de l'enseignant de la classe
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

    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $user = auth()->user();

        // Vérifier que l'étudiant est inscrit dans la classe
        if (! $classe->etudiants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        // Vérifier que l'étudiant n'est pas déjà dans un groupe de cette classe
        $dejaDansGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if ($dejaDansGroupe) {
            return back()->withErrors(['general' => 'Vous êtes déjà membre d\'un groupe dans cette classe.']);
        }

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'membres' => ['array'],
            'membres.*' => ['integer', 'exists:users,id'],
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        $groupe = Groupe::create([
            'nom' => $validated['nom'],
            'classe_id' => $classe->id,
            'created_by' => $user->id,
        ]);

        // Attacher le créateur + les membres sélectionnés
        $membres = array_unique(array_merge([$user->id], $validated['membres'] ?? []));
        $groupe->membres()->attach($membres);

        // Attacher les thématiques
        if (! empty($validated['thematiques'])) {
            $groupe->thematiques()->attach($validated['thematiques']);
        }

        return back()->with('success', 'Groupe créé avec succès.');
    }

    public function show(Classe $classe, Groupe $groupe): Response
    {
        if ($groupe->classe_id !== $classe->id) {
            abort(404);
        }

        $user = auth()->user();

        $estMembre     = $groupe->membres()->where('users.id', $user->id)->exists();
        $estEnseignant = $groupe->classe->enseignant_id === $user->id;
        $estAdmin      = $user->isAdmin();

        if (! $estMembre && ! $estEnseignant && ! $estAdmin) {
            abort(403);
        }

        $groupe->load([
            'classe',
            'membres',
            'thematiques',
            'notes.auteur',
            'createur',
            'medias.auteur',
        ]);

        $thematiquesDispo = $groupe->classe->enseignant
            ->thematiques()
            ->get(['id', 'nom', 'periode_historique']);

        // Étudiants de la classe pas encore dans ce groupe (pour l'invitation)
        $membreIds = $groupe->membres->pluck('id');
        $etudiantsDispo = $groupe->classe->etudiants()
            ->whereNotIn('users.id', $membreIds)
            ->get(['users.id', 'prenom', 'nom']);

        return Inertia::render('Groupes/Show', [
            'groupe'           => $groupe,
            'estMembre'        => $estMembre,
            'estEnseignant'    => $estEnseignant,
            'estCreateur'      => $groupe->created_by === $user->id,
            'thematiquesDispo' => $thematiquesDispo,
            'etudiantsDispo'   => $etudiantsDispo,
        ]);
    }

    public function updateMembres(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        if ($groupe->classe_id !== $classe->id) {
            abort(404);
        }

        $user = auth()->user();

        if ($groupe->created_by !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'ajouter'   => ['array'],
            'ajouter.*' => ['integer', 'exists:users,id'],
            'retirer'   => ['array'],
            'retirer.*' => ['integer', 'exists:users,id'],
        ]);

        if (! empty($validated['ajouter'])) {
            $groupe->membres()->syncWithoutDetaching($validated['ajouter']);
        }

        // Le créateur ne peut pas se retirer
        $aRetirer = array_diff($validated['retirer'] ?? [], [$user->id]);
        if (! empty($aRetirer)) {
            $groupe->membres()->detach($aRetirer);
        }

        return back()->with('success', 'Membres mis à jour.');
    }

    public function updateThematiques(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        if ($groupe->classe_id !== $classe->id) {
            abort(404);
        }

        $user = auth()->user();

        if (! $groupe->membres()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'thematiques'   => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        $groupe->thematiques()->sync($validated['thematiques'] ?? []);

        return back()->with('success', 'Thématiques mises à jour.');
    }

    public function storeNote(Request $request, Groupe $groupe): RedirectResponse
    {
        $user = auth()->user();

        if (! $groupe->membres()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        GroupeNote::create([
            'groupe_id' => $groupe->id,
            'user_id' => $user->id,
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', 'Note publiée.');
    }

    public function destroyNote(Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        $user = auth()->user();

        if ($note->user_id !== $user->id) {
            abort(403);
        }

        $note->delete();

        return back()->with('success', 'Note supprimée.');
    }

    public function destroy(Classe $classe, Groupe $groupe): RedirectResponse
    {
        $user = auth()->user();

        if ($groupe->created_by !== $user->id) {
            abort(403);
        }

        $groupe->delete();

        return redirect()->route('groupes.index', $classe)->with('success', 'Groupe supprimé.');
    }
}
