<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGrilleCorrectionRequest;
use App\Http\Requests\UpdateGrilleCorrectionRequest;
use App\Models\Classe;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GrilleCorrectionController extends Controller
{
    /**
     * Affiche la page de gestion de la grille de correction d'une classe.
     *
     * Sert à la fois de page de création et d'édition selon si la grille existe déjà.
     * L'enseignant doit être propriétaire de la classe.
     */
    public function edit(Classe $classe): Response
    {
        abort_if($classe->enseignant_id !== auth()->id(), 403);

        $grille = $classe->grille?->load(['criteres', 'malus']);

        return Inertia::render('Classes/Grille', [
            'classe' => $classe->only(['id', 'nom_cours', 'code', 'groupe']),
            'grille' => $grille,
        ]);
    }

    /**
     * Enregistre une nouvelle grille de correction pour une classe.
     *
     * La classe ne doit pas encore avoir de grille (contrainte unique DB + authorize).
     */
    public function store(StoreGrilleCorrectionRequest $request, Classe $classe): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $classe): void {
            $grille = $classe->grille()->create([
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);

            $criteres = collect($data['criteres'])->values()->map(fn (array $c, int $i): array => [
                'label' => $c['label'],
                'ponderation' => $c['ponderation'],
                'ordre' => $i,
            ]);

            $grille->criteres()->createMany($criteres->all());

            if (! empty($data['malus'])) {
                $malus = collect($data['malus'])->values()->map(fn (array $m, int $i): array => [
                    'label' => $m['label'],
                    'deduction' => $m['deduction'],
                    'description' => $m['description'] ?? null,
                    'ordre' => $i,
                ]);

                $grille->malus()->createMany($malus->all());
            }
        });

        return redirect()->route('classes.show', $classe)->with('success', 'Grille de correction créée.');
    }

    /**
     * Met à jour la grille de correction existante d'une classe.
     *
     * Stratégie de synchronisation des critères et malus :
     * - Ligne avec `id` → mise à jour du label/pondération/déduction
     * - Ligne sans `id`  → création
     * - Ligne absente    → suppression (cascade sur projet_grille_notes et projet_grille_malus)
     */
    public function update(UpdateGrilleCorrectionRequest $request, Classe $classe): RedirectResponse
    {
        $grille = $classe->grille;
        $data = $request->validated();

        DB::transaction(function () use ($data, $grille): void {
            $grille->update([
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);

            // Synchronisation des critères
            $criteresPayload = collect($data['criteres'])->values();
            $criteresIds = $criteresPayload->pluck('id')->filter()->values();

            $grille->criteres()->whereNotIn('id', $criteresIds)->delete();

            foreach ($criteresPayload as $i => $c) {
                if (! empty($c['id'])) {
                    GrilleCritere::where('id', $c['id'])
                        ->where('grille_id', $grille->id)
                        ->update([
                            'label' => $c['label'],
                            'ponderation' => $c['ponderation'],
                            'ordre' => $i,
                        ]);
                } else {
                    $grille->criteres()->create([
                        'label' => $c['label'],
                        'ponderation' => $c['ponderation'],
                        'ordre' => $i,
                    ]);
                }
            }

            // Synchronisation des malus
            $malusPayload = collect($data['malus'] ?? [])->values();
            $malusIds = $malusPayload->pluck('id')->filter()->values();

            $grille->malus()->whereNotIn('id', $malusIds)->delete();

            foreach ($malusPayload as $i => $m) {
                if (! empty($m['id'])) {
                    GrilleMalus::where('id', $m['id'])
                        ->where('grille_id', $grille->id)
                        ->update([
                            'label' => $m['label'],
                            'deduction' => $m['deduction'],
                            'description' => $m['description'] ?? null,
                            'ordre' => $i,
                        ]);
                } else {
                    $grille->malus()->create([
                        'label' => $m['label'],
                        'deduction' => $m['deduction'],
                        'description' => $m['description'] ?? null,
                        'ordre' => $i,
                    ]);
                }
            }
        });

        return redirect()->route('classes.show', $classe)->with('success', 'Grille de correction mise à jour.');
    }

    /**
     * Supprime la grille de correction d'une classe.
     *
     * La suppression est en cascade sur les critères, malus, et toutes les notes associées.
     */
    public function destroy(Classe $classe): RedirectResponse
    {
        abort_if($classe->enseignant_id !== auth()->id(), 403);

        $classe->grille?->delete();

        return redirect()->route('classes.show', $classe)->with('success', 'Grille de correction supprimée.');
    }
}
