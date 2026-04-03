<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatePonderations
{
    /**
     * Vérifie que la somme des pondérations des critères est exactement égale à 100.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $total = collect($this->input('criteres', []))->sum('ponderation');

            if ((int) $total !== 100) {
                $v->errors()->add('criteres', "La somme des pondérations doit être égale à 100 (actuel : {$total}).");
            }
        });
    }
}
