<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EpoquesHistoriquesSeeder extends Seeder
{
    /**
     * Peuple la table `epoques_historiques` avec les grandes périodes
     * de l'histoire du Québec, dans l'ordre chronologique.
     */
    public function run(): void
    {
        $epoques = [
            [
                'nom' => 'Premières Nations',
                'annee_debut' => null,
                'annee_fin' => 1534,
                'ordre' => 1,
            ],
            [
                'nom' => 'Nouvelle-France',
                'annee_debut' => 1534,
                'annee_fin' => 1763,
                'ordre' => 2,
            ],
            [
                'nom' => 'Régime britannique',
                'annee_debut' => 1763,
                'annee_fin' => 1840,
                'ordre' => 3,
            ],
            [
                'nom' => 'Union des Canadas',
                'annee_debut' => 1840,
                'annee_fin' => 1867,
                'ordre' => 4,
            ],
            [
                'nom' => 'Confédération et industrialisation',
                'annee_debut' => 1867,
                'annee_fin' => 1920,
                'ordre' => 5,
            ],
            [
                'nom' => 'L\'entre-deux-guerres',
                'annee_debut' => 1920,
                'annee_fin' => 1945,
                'ordre' => 6,
            ],
            [
                'nom' => 'La Grande Noirceur',
                'annee_debut' => 1945,
                'annee_fin' => 1960,
                'ordre' => 7,
            ],
            [
                'nom' => 'La Révolution tranquille',
                'annee_debut' => 1960,
                'annee_fin' => 1980,
                'ordre' => 8,
            ],
            [
                'nom' => 'Référendums et mondialisation',
                'annee_debut' => 1980,
                'annee_fin' => 2000,
                'ordre' => 9,
            ],
            [
                'nom' => 'Québec contemporain',
                'annee_debut' => 2000,
                'annee_fin' => null,
                'ordre' => 10,
            ],
        ];

        foreach ($epoques as $epoque) {
            DB::table('epoques_historiques')->updateOrInsert(
                ['nom' => $epoque['nom']],
                array_merge($epoque, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
