<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\EcheancierEtape;
use Illuminate\Database\Seeder;

class EcheancierEtapesSeeder extends Seeder
{
    /**
     * Étapes types de l'échéancier "Votre histoire, notre histoire" (sem. 1–13).
     * Source : consigne .docx du projet 2025.
     *
     * @var array<int, list<string>>
     */
    private const ETAPES_PAR_SEMAINE = [
        1 => [
            'Présentation des consignes du travail de session',
            'Formation des équipes',
            'Choix des thématiques',
        ],
        2 => [
            'Choix définitif de la thématique',
            'Début des recherches bibliographiques',
            'Répartition des tâches dans l\'équipe',
        ],
        3 => [
            'Remise du plan provisoire (formatif)',
        ],
        4 => [
            'Poursuite des recherches',
            'Rédaction des sections Introduction et Développement',
        ],
        5 => [
            'Remise de la fiche de recherche (15 %)',
            'Élaboration du schéma d\'entrevue',
            'Jumelage avec un témoin / participant',
            'Prise de rendez-vous pour l\'entrevue',
            'Construction de la grille d\'entrevue',
        ],
        6 => [
            'Validation du schéma d\'entrevue avec l\'enseignant',
        ],
        7 => [
            'Remise du schéma d\'entrevue (5 %)',
        ],
        8 => [
            'Correction des questions d\'entrevue selon les commentaires',
            'Confirmation de la date d\'entrevue',
            'Préparation du matériel d\'enregistrement',
        ],
        9 => [
            'Passation des entrevues (première vague)',
        ],
        10 => [
            'Passation des entrevues (deuxième vague)',
            'Saisie et transcription des entrevues',
        ],
        11 => [
            'Analyse des données recueillies',
            'Rédaction du plan détaillé du rapport',
        ],
        12 => [
            'Rédaction du rapport final',
            'Intégration des extraits d\'entrevues dans le développement',
        ],
        13 => [
            'Remise du rapport final (25 %)',
        ],
    ];

    /**
     * Peuple la table `echeancier_etapes` pour toutes les classes existantes.
     *
     * Si une classe possède déjà des étapes, elle est ignorée pour éviter les doublons.
     */
    public function run(): void
    {
        $classes = Classe::all();

        foreach ($classes as $classe) {
            // Éviter les doublons si le seeder est relancé
            if ($classe->echeancierEtapes()->exists()) {
                continue;
            }

            $this->creerEtapesPourClasse($classe);
        }
    }

    /**
     * Crée toutes les étapes de l'échéancier pour une classe donnée.
     */
    public function creerEtapesPourClasse(Classe $classe): void
    {
        $inserts = [];

        foreach (self::ETAPES_PAR_SEMAINE as $semaine => $etapes) {
            foreach ($etapes as $ordre => $etape) {
                $inserts[] = [
                    'classe_id' => $classe->id,
                    'semaine' => $semaine,
                    'etape' => $etape,
                    'is_done' => false,
                    'ordre' => $ordre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        EcheancierEtape::insert($inserts);
    }
}
