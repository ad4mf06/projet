<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\EcheancierEtape;
use App\Models\EcheancierEtudiantProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcheancierController extends Controller
{
    /**
     * Ajoute une nouvelle étape à l'échéancier d'une classe.
     *
     * Réservé à l'enseignant de la classe et aux admins.
     */
    public function store(Request $request, Classe $classe): RedirectResponse
    {
        Gate::authorize('update', $classe);

        $validated = $request->validate([
            'semaine' => ['required', 'integer', 'min:1', 'max:15'],
            'etape' => ['required', 'string', 'max:500'],
        ]);

        // L'ordre est défini comme le prochain dans la semaine
        $ordre = EcheancierEtape::where('classe_id', $classe->id)
            ->where('semaine', $validated['semaine'])
            ->max('ordre') ?? -1;

        EcheancierEtape::create([
            'classe_id' => $classe->id,
            'semaine' => $validated['semaine'],
            'etape' => $validated['etape'],
            'is_done' => false,
            'ordre' => $ordre + 1,
        ]);

        return back();
    }

    /**
     * Met à jour le texte d'une étape de l'échéancier.
     *
     * Réservé à l'enseignant de la classe et aux admins.
     */
    public function update(Request $request, Classe $classe, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->classe_id !== $classe->id, 404);
        Gate::authorize('update', $classe);

        $validated = $request->validate([
            'etape' => ['required', 'string', 'max:500'],
        ]);

        $etape->update($validated);

        return back();
    }

    /**
     * Supprime une étape de l'échéancier.
     *
     * Réservé à l'enseignant de la classe et aux admins.
     */
    public function destroy(Classe $classe, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->classe_id !== $classe->id, 404);
        Gate::authorize('update', $classe);

        $etape->delete();

        return back();
    }

    /**
     * Supprime toutes les étapes de l'échéancier d'une classe.
     *
     * Réservé à l'enseignant de la classe et aux admins.
     */
    public function destroyAll(Classe $classe): RedirectResponse
    {
        Gate::authorize('update', $classe);

        $classe->echeancierEtapes()->delete();

        return back();
    }

    /**
     * Bascule l'état is_done d'une étape (fait / non fait).
     *
     * Réservé à l'enseignant de la classe et aux admins.
     */
    public function toggleDone(Classe $classe, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->classe_id !== $classe->id, 404);
        Gate::authorize('update', $classe);

        $etape->update(['is_done' => ! $etape->is_done]);

        return back();
    }

    /**
     * Bascule la progression personnelle de l'étudiant connecté pour une étape.
     *
     * Chaque étudiant inscrit dans la classe gère uniquement son propre avancement.
     */
    public function toggleEtudiant(Classe $classe, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->classe_id !== $classe->id, 404);

        $user = auth()->user();

        // Vérification d'inscription à la classe
        abort_unless($classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $progression = EcheancierEtudiantProgress::firstOrCreate(
            ['echeancier_etape_id' => $etape->id, 'user_id' => $user->id],
            ['is_done' => false],
        );

        $progression->update(['is_done' => ! $progression->is_done]);

        return back();
    }
}
