<?php

namespace App\Observers;

use App\Models\MuseeMeta;
use App\Models\ProjetRecherche;

class ProjetRechercheObserver
{
    /**
     * Auto-crée un enregistrement MuseeMeta lors de la création d'un ProjetRecherche
     * de type musée, avec un slug garanti unique basé sur le groupe et le type de projet.
     */
    public function created(ProjetRecherche $projetRecherche): void
    {
        $projetRecherche->loadMissing('typeProjet');

        if (! $projetRecherche->typeProjet?->isMusee()) {
            return;
        }

        $slugBase = "musee-{$projetRecherche->groupe_id}-{$projetRecherche->type_projet_id}";

        MuseeMeta::firstOrCreate(
            ['projet_recherche_id' => $projetRecherche->id],
            ['slug' => MuseeMeta::genererSlug($slugBase)],
        );
    }
}
