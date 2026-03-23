<?php

namespace App\Http\Requests;

use App\Models\ProjetNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProjetNoteRequest extends FormRequest
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
     * Retourne les règles de validation pour l'ajout ou la modification d'une note.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'critere' => ['required', 'string', Rule::in(array_keys(ProjetNote::CRITERES))],
            // Valeurs de la grille : 0 (mauvais), 2 (passable), 3 (bon), 4 (excellent)
            'note' => ['required', 'integer', Rule::in([0, 2, 3, 4])],
            // Toujours requis : les notes sont attribuées par étudiant
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
