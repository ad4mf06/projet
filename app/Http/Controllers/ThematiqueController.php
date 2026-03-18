<?php

namespace App\Http\Controllers;

use App\Models\Thematique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThematiqueController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'periode_historique' => ['nullable', 'string', 'max:255'],
        ]);

        auth()->user()->thematiques()->create($validated);

        return back()->with('success', 'Thématique créée avec succès.');
    }

    public function update(Request $request, Thematique $thematique): RedirectResponse
    {
        $this->authorizeThematique($thematique);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'periode_historique' => ['nullable', 'string', 'max:255'],
        ]);

        $thematique->update($validated);

        return back()->with('success', 'Thématique mise à jour.');
    }

    public function destroy(Thematique $thematique): RedirectResponse
    {
        $this->authorizeThematique($thematique);

        $thematique->delete();

        return back()->with('success', 'Thématique supprimée.');
    }

    private function authorizeThematique(Thematique $thematique): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($thematique->enseignant_id !== $user->id) {
            abort(403, 'Vous ne pouvez pas modifier cette thématique.');
        }
    }
}
