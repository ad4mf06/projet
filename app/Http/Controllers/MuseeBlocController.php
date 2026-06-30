<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseeBlocController extends Controller
{
    /**
     * Vérifie que la section appartient bien au TypeProjet et au cours,
     * et que le projet musée de ce groupe existe. Retourne le ProjetRecherche.
     *
     * @throws HttpException
     */
    private function verifierAcces(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): ProjetRecherche {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();
    }

    /**
     * Crée un nouveau bloc dans la section donnée du projet musée.
     *
     * L'ordre est auto-incrémenté (max existant + 1).
     *
     * @throws HttpException
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);

        $request->validate([
            'type' => ['required', 'string', 'in:texte,image,separateur,carrousel'],
        ]);

        $maxOrdre = MuseeBloc::where('projet_recherche_id', $projet->id)
            ->where('section_id', $section->id)
            ->max('ordre') ?? 0;

        MuseeBloc::create([
            'projet_recherche_id' => $projet->id,
            'section_id' => $section->id,
            'type' => $request->input('type'),
            'contenu' => $this->contenuParDefaut($request->input('type')),
            'ordre' => $maxOrdre + 1,
        ]);

        return back()->with('success', 'Bloc ajouté.');
    }

    /**
     * Met à jour le contenu JSON d'un bloc existant.
     *
     * @throws HttpException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $contenuValide = $this->validerContenu($request, $bloc->type);

        $bloc->update(['contenu' => $contenuValide]);

        return back()->with('success', 'Bloc mis à jour.');
    }

    /**
     * Supprime un bloc et renuméroter les suivants dans la même section.
     *
     * @throws HttpException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $ordreSupp = $bloc->ordre;
        $bloc->delete();

        // Renuméroter les blocs dont l'ordre est supérieur au bloc supprimé
        MuseeBloc::where('projet_recherche_id', $projet->id)
            ->where('section_id', $section->id)
            ->where('ordre', '>', $ordreSupp)
            ->decrement('ordre');

        return back()->with('success', 'Bloc supprimé.');
    }

    /**
     * Réordonne les blocs d'une section selon le tableau d'IDs fourni.
     *
     * @throws HttpException
     */
    public function reorder(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);

        $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['integer'],
        ]);

        foreach ($request->input('ordre') as $position => $blocId) {
            MuseeBloc::where('id', $blocId)
                ->where('projet_recherche_id', $projet->id)
                ->where('section_id', $section->id)
                ->update(['ordre' => $position + 1]);
        }

        return back()->with('success', 'Blocs réordonnés.');
    }

    /**
     * Retourne le contenu JSON par défaut pour un nouveau bloc selon son type.
     */
    private function contenuParDefaut(string $type): ?array
    {
        return match ($type) {
            MuseeBloc::TYPE_TEXTE => ['html' => ''],
            MuseeBloc::TYPE_IMAGE => ['image_id' => null, 'legende' => '', 'alt' => ''],
            MuseeBloc::TYPE_CARROUSEL => ['images' => []],
            MuseeBloc::TYPE_SEPARATEUR => null,
            default => null,
        };
    }

    /**
     * Valide et retourne le contenu JSON selon le type du bloc.
     */
    private function validerContenu(Request $request, string $type): ?array
    {
        return match ($type) {
            MuseeBloc::TYPE_TEXTE => (function () use ($request): array {
                $request->validate([
                    'html' => ['nullable', 'string'],
                    'image_ancree' => ['nullable', 'array'],
                    'image_ancree.image_id' => ['nullable', 'integer', 'exists:musee_images,id'],
                    'image_ancree.position' => ['nullable', 'string', 'in:gauche,droite'],
                ]);

                $result = ['html' => $request->input('html', '')];

                // N'inclure image_ancree que si explicitement fourni pour ne pas écraser les données existantes
                if ($request->has('image_ancree')) {
                    $result['image_ancree'] = $request->input('image_ancree');
                }

                return $result;
            })(),

            MuseeBloc::TYPE_IMAGE => (function () use ($request): array {
                $request->validate([
                    'image_id' => ['nullable', 'integer', 'exists:musee_images,id'],
                    'legende' => ['nullable', 'string', 'max:500'],
                    'alt' => ['nullable', 'string', 'max:255'],
                ]);

                return [
                    'image_id' => $request->input('image_id'),
                    'legende' => $request->input('legende', ''),
                    'alt' => $request->input('alt', ''),
                ];
            })(),

            MuseeBloc::TYPE_CARROUSEL => (function () use ($request): array {
                $request->validate([
                    'images' => ['nullable', 'array'],
                    'images.*.image_id' => ['required', 'integer', 'exists:musee_images,id'],
                    'images.*.legende' => ['nullable', 'string', 'max:500'],
                    'images.*.alt' => ['nullable', 'string', 'max:255'],
                ]);

                return ['images' => $request->input('images', [])];
            })(),

            MuseeBloc::TYPE_SEPARATEUR => null,

            default => null,
        };
    }
}
