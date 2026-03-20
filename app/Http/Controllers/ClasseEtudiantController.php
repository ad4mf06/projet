<?php

namespace App\Http\Controllers;

use App\Actions\CreateEtudiantAction;
use App\Actions\ImportEtudiantsAction;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClasseEtudiantController extends Controller
{
    public function __construct(
        private readonly CreateEtudiantAction $createEtudiant,
        private readonly ImportEtudiantsAction $importEtudiants,
    ) {}

    /**
     * Ajoute manuellement un étudiant à la classe.
     *
     * Trouve ou crée l'étudiant via CreateEtudiantAction, puis l'attache à la classe
     * si ce n'est pas déjà fait.
     */
    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorize('update', $classe);

        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'no_da' => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $etudiant = $this->createEtudiant->execute(
            $validated['no_da'],
            $validated['prenom'],
            $validated['nom'],
            $validated['email'] ?? null,
        );

        if (! $classe->etudiants()->whereKey($etudiant->id)->exists()) {
            $classe->etudiants()->attach($etudiant->id, [
                'statut_cours' => $validated['statut_cours'] ?? null,
            ]);
        }

        return back()->with('success', __('etudiant.added'));
    }

    /**
     * Met à jour les informations d'un étudiant dans la classe.
     */
    public function update(Request $request, Classe $classe, User $etudiant): RedirectResponse
    {
        $this->authorize('update', $classe);

        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($etudiant->id)],
            'no_da' => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
        ]);

        $etudiant->update([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'no_da' => $validated['no_da'],
        ]);

        $classe->etudiants()->updateExistingPivot($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
        ]);

        return back()->with('success', __('etudiant.updated'));
    }

    /**
     * Retire un étudiant de la classe (sans supprimer son compte).
     */
    public function destroy(Classe $classe, User $etudiant): RedirectResponse
    {
        $this->authorize('update', $classe);

        $classe->etudiants()->detach($etudiant->id);

        return back()->with('success', __('etudiant.removed'));
    }

    /**
     * Importe des étudiants depuis un fichier CSV dans la classe.
     *
     * Délègue entièrement à ImportEtudiantsAction.
     */
    public function import(Request $request, Classe $classe): RedirectResponse
    {
        $this->authorize('update', $classe);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $content = file_get_contents($request->file('csv')->getPathname());
        $created = $this->importEtudiants->execute($classe, $content);

        return back()->with('success', __('etudiant.imported', ['count' => $created]));
    }
}
