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
            ->get(['id', 'label', 'ordre', 'musee_contraintes', 'musee_layout', 'musee_canevas']);

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
     * Met à jour le canevas de zones d'une section musée.
     *
     * Le canevas remplace l'ancien système layout + contraintes par un document
     * structuré décrivant les zones positionnées que l'enseignant a dessinées.
     * Les étudiants rempliront chacune de ces zones avec le type de contenu attendu.
     *
     * @param  array{hauteur_vw: int, gap: int, zones: array<array{id: string, type: string, label: string, x: float, y: float, w: float, h: float, ordre_mobile: int}>}|null  $canevas
     */
    public function updateCanevas(
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
            'canevas'                      => ['nullable', 'array'],
            'canevas.hauteur_vw'           => ['required_with:canevas', 'integer', 'min:20', 'max:200'],
            // Écart en px entre zones (0 = pas d'espace, max 20 px).
            'canevas.gap'                  => ['required_with:canevas', 'integer', 'min:0', 'max:20'],
            'canevas.zones'                => ['required_with:canevas', 'array', 'max:20'],
            'canevas.zones.*.id'           => ['required', 'string', 'max:36'],
            'canevas.zones.*.type'         => ['required', 'string', 'in:texte,image,carrousel,video,audio,vide'],
            'canevas.zones.*.label'        => ['required', 'string', 'max:100'],
            'canevas.zones.*.x'            => ['required', 'numeric', 'min:0', 'max:100'],
            'canevas.zones.*.y'            => ['required', 'numeric', 'min:0', 'max:100'],
            'canevas.zones.*.w'            => ['required', 'numeric', 'min:5', 'max:100'],
            'canevas.zones.*.h'            => ['required', 'numeric', 'min:5', 'max:100'],
            'canevas.zones.*.ordre_mobile' => ['required', 'integer', 'min:1'],
        ]);

        $section->update(['musee_canevas' => $data['canevas'] ?? null]);

        return back()->with('success', 'Canevas mis à jour.');
    }

    /**
     * Met à jour les contraintes de blocs et le layout d'une section musée.
     *
     * Les contraintes décrivent les types de blocs attendus dans la section.
     * Le layout définit si la section s'affiche en 1 ou 2 colonnes (avec ratio).
     *
     * @param  array{type: string, requis: bool, label: string}[]  $contraintes
     * @param  array{nb_colonnes: int, ratio: string}|null  $layout
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
            // Layout 2 colonnes — null = 1 colonne pleine largeur (défaut)
            'layout' => ['nullable', 'array'],
            'layout.nb_colonnes' => ['required_with:layout', 'integer', 'in:1,2'],
            'layout.ratio' => ['nullable', 'string', 'in:50-50,60-40,40-60,70-30,30-70'],
        ]);

        // Normaliser : layout 1 colonne → null pour éviter du bruit en BD
        $layout = $data['layout'] ?? null;
        if ($layout !== null && ($layout['nb_colonnes'] ?? 1) === 1) {
            $layout = null;
        }

        $section->update([
            'musee_contraintes' => $data['contraintes'],
            'musee_layout' => $layout,
        ]);

        return back()->with('success', 'Contraintes mises à jour.');
    }
}
