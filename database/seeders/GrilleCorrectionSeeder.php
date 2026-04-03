<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use Illuminate\Database\Seeder;

class GrilleCorrectionSeeder extends Seeder
{
    /**
     * Critères de correction du projet de recherche "Votre histoire, notre histoire".
     * La somme des pondérations est 100.
     *
     * @var array<int, array{label: string, ponderation: int}>
     */
    private const CRITERES = [
        ['label' => 'Introduction : mise en contexte, problématique et annonce du plan',  'ponderation' => 15],
        ['label' => 'Qualité et diversité des sources bibliographiques (min. 5 sources)',  'ponderation' => 15],
        ['label' => 'Développement : profondeur de l\'analyse et pertinence des arguments', 'ponderation' => 30],
        ['label' => 'Intégration et exploitation des données d\'entrevue',                  'ponderation' => 20],
        ['label' => 'Conclusion : synthèse et ouverture',                                  'ponderation' => 10],
        ['label' => 'Présentation, structure et respect des normes de mise en page',        'ponderation' => 10],
    ];

    /**
     * Malus applicables par l'enseignant lors de la correction.
     *
     * @var array<int, array{label: string, deduction: float, description: string}>
     */
    private const MALUS = [
        [
            'label' => 'Fautes de français',
            'deduction' => 0.5,
            'description' => '0,5 point déduit par faute (orthographe, grammaire, syntaxe), jusqu\'à un maximum de 5 points.',
        ],
        [
            'label' => 'Remise en retard',
            'deduction' => 5.0,
            'description' => '5 points déduits par jour de retard.',
        ],
        [
            'label' => 'Non-respect des normes de présentation',
            'deduction' => 2.0,
            'description' => 'Police, marges, espacement ou numérotation non conformes au guide de présentation.',
        ],
        [
            'label' => 'Absence d\'entrevue',
            'deduction' => 10.0,
            'description' => 'L\'équipe n\'a pas réalisé d\'entrevue avec un témoin ou participant.',
        ],
    ];

    /**
     * Crée la grille de correction pour chaque classe qui n'en possède pas encore.
     */
    public function run(): void
    {
        $classes = Classe::all();

        foreach ($classes as $classe) {
            if ($classe->grille()->exists()) {
                continue;
            }

            $this->creerGrillePourClasse($classe);
        }
    }

    /**
     * Crée la grille, ses critères et ses malus pour une classe donnée.
     */
    private function creerGrillePourClasse(Classe $classe): void
    {
        /** @var GrilleCorrection $grille */
        $grille = GrilleCorrection::create([
            'classe_id' => $classe->id,
            'nom' => 'Grille de correction — '.$classe->nom_cours,
            'description' => 'Grille officielle du projet de recherche "Votre histoire, notre histoire" (session en cours). Total : 100 points.',
        ]);

        foreach (self::CRITERES as $ordre => $critere) {
            GrilleCritere::create([
                'grille_id' => $grille->id,
                'label' => $critere['label'],
                'ponderation' => $critere['ponderation'],
                'ordre' => $ordre + 1,
            ]);
        }

        foreach (self::MALUS as $ordre => $malus) {
            GrilleMalus::create([
                'grille_id' => $grille->id,
                'label' => $malus['label'],
                'deduction' => $malus['deduction'],
                'description' => $malus['description'],
                'ordre' => $ordre + 1,
            ]);
        }
    }
}
