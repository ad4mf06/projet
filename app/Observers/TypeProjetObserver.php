<?php

namespace App\Observers;

use App\Models\MuseeTemplate;
use App\Models\TypeProjet;

class TypeProjetObserver
{
    /**
     * Crée automatiquement un MuseeTemplate avec les valeurs par défaut
     * dès qu'un TypeProjet de type 'musee' est créé.
     *
     * Les valeurs par défaut (polices, couleurs, palette) sont définies
     * dans la migration — on s'appuie sur les defaults DB ici.
     */
    public function created(TypeProjet $typeProjet): void
    {
        if (! $typeProjet->isMusee()) {
            return;
        }

        MuseeTemplate::firstOrCreate(
            ['type_projet_id' => $typeProjet->id],
        );
    }
}
