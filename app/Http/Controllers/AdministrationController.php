<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function index(): Response
    {
        $enseignants = User::where('role', 'enseignant')
            ->withCount(['classes', 'thematiques'])
            ->orderBy('nom')
            ->get();

        $stats = [
            'total_enseignants' => User::where('role', 'enseignant')->count(),
            'total_classes' => Classe::count(),
            'total_etudiants' => User::where('role', 'etudiant')->count(),
        ];

        return Inertia::render('Administration/Index', [
            'enseignants' => $enseignants,
            'stats' => $stats,
        ]);
    }

    public function storeEnseignant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
        ]);

        User::create([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'password' => 'Enseignant',
            'role' => 'enseignant',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Enseignant créé avec succès.');
    }

    public function updateEnseignant(Request $request, User $enseignant): RedirectResponse
    {
        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($enseignant->id)],
        ]);

        $enseignant->update($validated);

        return back()->with('success', 'Enseignant mis à jour avec succès.');
    }

    public function destroyEnseignant(User $enseignant): RedirectResponse
    {
        $enseignant->delete();

        return back()->with('success', 'Enseignant supprimé avec succès.');
    }
}
