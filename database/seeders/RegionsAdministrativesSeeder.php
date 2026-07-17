<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionsAdministrativesSeeder extends Seeder
{
    /**
     * Peuple la table `regions_administratives` avec les 17 régions
     * administratives officielles du Québec, dans l'ordre numérique
     * du code de région attribué par le gouvernement du Québec.
     */
    public function run(): void
    {
        $regions = [
            ['code_region' => '01', 'nom' => 'Bas-Saint-Laurent', 'ordre' => 1],
            ['code_region' => '02', 'nom' => 'Saguenay–Lac-Saint-Jean', 'ordre' => 2],
            ['code_region' => '03', 'nom' => 'Capitale-Nationale', 'ordre' => 3],
            ['code_region' => '04', 'nom' => 'Mauricie', 'ordre' => 4],
            ['code_region' => '05', 'nom' => 'Estrie', 'ordre' => 5],
            ['code_region' => '06', 'nom' => 'Montréal', 'ordre' => 6],
            ['code_region' => '07', 'nom' => 'Outaouais', 'ordre' => 7],
            ['code_region' => '08', 'nom' => 'Abitibi-Témiscamingue', 'ordre' => 8],
            ['code_region' => '09', 'nom' => 'Côte-Nord', 'ordre' => 9],
            ['code_region' => '10', 'nom' => 'Nord-du-Québec', 'ordre' => 10],
            ['code_region' => '11', 'nom' => 'Gaspésie–Îles-de-la-Madeleine', 'ordre' => 11],
            ['code_region' => '12', 'nom' => 'Chaudière-Appalaches', 'ordre' => 12],
            ['code_region' => '13', 'nom' => 'Laval', 'ordre' => 13],
            ['code_region' => '14', 'nom' => 'Lanaudière', 'ordre' => 14],
            ['code_region' => '15', 'nom' => 'Laurentides', 'ordre' => 15],
            ['code_region' => '16', 'nom' => 'Montérégie', 'ordre' => 16],
            ['code_region' => '17', 'nom' => 'Centre-du-Québec', 'ordre' => 17],
        ];

        foreach ($regions as $region) {
            DB::table('regions_administratives')->updateOrInsert(
                ['code_region' => $region['code_region']],
                array_merge($region, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
