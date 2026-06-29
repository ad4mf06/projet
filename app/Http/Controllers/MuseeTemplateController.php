<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MuseeTemplateController extends Controller
{
    /**
     * Affiche la page d'édition du template visuel pour un TypeProjet musée.
     */
    public function edit(Cours $cours, TypeProjet $typeProjet): Response
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $template = $typeProjet->museeTemplate()->firstOrCreate(
            ['type_projet_id' => $typeProjet->id],
        );

        return Inertia::render('Musee/Template/Edit', [
            'cours' => $cours,
            'typeProjet' => $typeProjet,
            'template' => $template,
        ]);
    }

    /**
     * Met à jour le template visuel (polices, couleurs, palette, thème).
     *
     * Les palettes prédéfinies écrasent les couleurs individuelles côté serveur
     * pour garantir la cohérence — la palette sélectionnée est mémorisée afin
     * que l'UI puisse la marquer comme active au prochain chargement.
     */
    public function update(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $data = $request->validate([
            'font_titre_page' => ['required', 'string', 'max:100'],
            'font_sous_titre' => ['required', 'string', 'max:100'],
            'font_titre_section' => ['required', 'string', 'max:100'],
            'font_corps' => ['required', 'string', 'max:100'],
            'font_legende' => ['required', 'string', 'max:100'],
            'couleur_fond' => ['required', 'string', 'max:20'],
            'couleur_titre' => ['required', 'string', 'max:20'],
            'couleur_corps' => ['required', 'string', 'max:20'],
            'couleur_accent' => ['required', 'string', 'max:20'],
            'couleur_lien_externe' => ['required', 'string', 'max:20'],
            'palette' => ['nullable', 'string', 'max:50'],
            'theme' => ['required', 'string', 'in:clair,sombre'],
        ]);

        $typeProjet->museeTemplate()->updateOrCreate(
            ['type_projet_id' => $typeProjet->id],
            $data,
        );

        return back()->with('success', 'Template visuel mis à jour.');
    }
}
