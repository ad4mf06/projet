<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThematiquesGlobalesCegepSeeder extends Seeder
{
    /**
     * Peuple la table `thematiques` avec les grandes catégories thématiques
     * utilisées dans les cours d'histoire et de sciences sociales au CEGEP.
     *
     * Ces thématiques sont globales (etablissement_id et enseignant_id null)
     * et servent à catégoriser les projets musée indépendamment des établissements.
     */
    public function run(): void
    {
        $thematiques = [
            [
                'nom' => 'Politique et institutions',
                'description' => 'Gouvernements, pouvoirs, partis politiques, constitutions et institutions démocratiques.',
            ],
            [
                'nom' => 'Économie et développement',
                'description' => 'Évolution économique, commerce, ressources naturelles, industrialisation et conditions de travail.',
            ],
            [
                'nom' => 'Société et vie communautaire',
                'description' => 'Organisation sociale, famille, classes sociales, mouvements populaires et vie quotidienne.',
            ],
            [
                'nom' => 'Culture, arts et identité',
                'description' => 'Expressions artistiques, littérature, langue, patrimoine culturel et construction identitaire.',
            ],
            [
                'nom' => 'Territoire et peuplement',
                'description' => 'Occupation du territoire, migrations, urbanisation, démographie et aménagement.',
            ],
            [
                'nom' => 'Religion et spiritualité',
                'description' => 'Rôle de l\'Église, diversité religieuse, laïcisation et pratiques spirituelles.',
            ],
            [
                'nom' => 'Droits et libertés',
                'description' => 'Droits civiques, droits des femmes, droits autochtones, libertés fondamentales et luttes sociales.',
            ],
            [
                'nom' => 'Relations internationales',
                'description' => 'Diplomatie, traités, alliances, colonialisme et place du Québec et du Canada dans le monde.',
            ],
            [
                'nom' => 'Science, technologie et innovation',
                'description' => 'Découvertes scientifiques, inventions, progrès technologiques et leur impact sur la société.',
            ],
            [
                'nom' => 'Conflits et enjeux militaires',
                'description' => 'Guerres, rébellions, résistances armées et leurs conséquences politiques et sociales.',
            ],
        ];

        foreach ($thematiques as $thematique) {
            DB::table('thematiques')->updateOrInsert(
                ['nom' => $thematique['nom'], 'etablissement_id' => null],
                array_merge($thematique, [
                    'etablissement_id' => null,
                    'enseignant_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
