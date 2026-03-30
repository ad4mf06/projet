<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProjetCommentaireRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * La vérification enseignant est faite dans le controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour l'ajout ou la modification d'un commentaire.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Champs fixes, développement_{id} (paragraphe dynamique) ou conclusion_{user_id}
            'champ' => ['required', 'string', 'max:100', 'regex:/^(normes_presentation|introduction_amener|introduction_poser|introduction_diviser|developpement|developpement_\d+|references|ecriture|conclusion_\d+)$/'],
            'contenu' => ['required', 'string'],
        ];
    }
}
