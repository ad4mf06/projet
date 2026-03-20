<?php

namespace App\Http\Requests;

use App\Models\Thematique;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThematiqueRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à modifier cette thématique.
     * Délègue à ThematiquePolicy::update().
     */
    public function authorize(): bool
    {
        /** @var Thematique $thematique */
        $thematique = $this->route('thematique');

        return $this->user()->can('update', $thematique);
    }

    /**
     * Retourne les règles de validation pour la mise à jour d'une thématique.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'periode_historique' => ['nullable', 'string', 'max:255'],
        ];
    }
}
