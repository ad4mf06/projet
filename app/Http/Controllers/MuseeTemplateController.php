<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
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

        $sections = $typeProjet->sections()
            ->orderBy('ordre')
            ->get(['id', 'label', 'ordre', 'musee_contraintes']);

        return Inertia::render('Musee/Template/Edit', [
            'cours' => $cours,
            'typeProjet' => $typeProjet,
            'template' => $template,
            'sections' => $sections,
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

    /**
     * Met à jour les contraintes de blocs (wireframe) d'une section musée.
     *
     * Chaque contrainte décrit un type de bloc attendu dans la section,
     * avec son caractère obligatoire/optionnel et un libellé descriptif.
     *
     * @param  array{type: string, requis: bool, label: string}[]  $contraintes
     */
    public function updateContraintes(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $data = $request->validate([
            'contraintes' => ['present', 'array', 'max:20'],
            'contraintes.*.type' => ['required', 'string', 'in:texte,image,carrousel,video,separateur'],
            'contraintes.*.requis' => ['required', 'boolean'],
            'contraintes.*.label' => ['required', 'string', 'max:100'],
        ]);

        $section->update(['musee_contraintes' => $data['contraintes']]);

        return back()->with('success', 'Contraintes mises à jour.');
    }
}
