<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class EnseignantController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $classes = $user->classes()
            ->withCount('etudiants')
            ->orderBy('nom_cours')
            ->get();

        $thematiques = $user->thematiques()
            ->orderBy('nom')
            ->get();

        return Inertia::render('Enseignant/Index', [
            'classes' => $classes,
            'thematiques' => $thematiques,
        ]);
    }
}
