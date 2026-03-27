<?php

namespace App\Http\Controllers;

use App\Models\ProjetRecherche;
use Inertia\Inertia;
use Inertia\Response;

class EnseignantController extends Controller
{
    /**
     * Affiche le tableau de bord de l'enseignant avec ses classes, thématiques
     * et les travaux récemment remis par ses groupes.
     */
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

        // Les 10 travaux les plus récemment remis parmi les groupes de ce prof
        $travauxRemis = ProjetRecherche::whereNotNull('remis_le')
            ->whereHas('groupe.classe', fn ($q) => $q->where('enseignant_id', $user->id))
            ->with(['groupe' => fn ($q) => $q->with(['membres', 'classe'])])
            ->orderByDesc('remis_le')
            ->limit(10)
            ->get()
            ->map(fn (ProjetRecherche $projet) => [
                'id' => $projet->id,
                'titre_projet' => $projet->titre_projet,
                'remis_le' => $projet->remis_le->toIso8601String(),
                'groupe' => [
                    'id' => $projet->groupe->id,
                    'nom' => $projet->groupe->nom,
                    'classe_id' => $projet->groupe->classe_id,
                ],
                'membres' => $projet->groupe->membres
                    ->map->only('id', 'prenom', 'nom')
                    ->values(),
            ]);

        return Inertia::render('Enseignant/Index', [
            'classes' => $classes,
            'thematiques' => $thematiques,
            'travauxRemis' => $travauxRemis,
        ]);
    }
}
