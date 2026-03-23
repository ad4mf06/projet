<?php

namespace App\Actions;

use App\Models\Classe;
use Illuminate\Support\Facades\DB;

class ImportEtudiantsAction
{
    public function __construct(private readonly CreateEtudiantAction $createEtudiant) {}

    /**
     * Importe les étudiants d'un fichier CSV dans une classe.
     *
     * - Détecte et convertit l'encodage (Excel → UTF-8)
     * - Précharge les IDs inscrits pour éviter le N+1
     * - Enveloppe toutes les écritures dans une transaction DB
     *
     * @param  Classe  $classe  La classe cible
     * @param  string  $csvContent  Contenu brut du fichier CSV
     * @return int Nombre d'étudiants nouvellement ajoutés
     */
    public function execute(Classe $classe, string $csvContent): int
    {
        $csvContent = $this->normalizeEncoding($csvContent);

        // Lire toutes les lignes d'abord — fermer le fichier temporaire avant la transaction
        $rows = $this->parseCsvRows($csvContent);

        // Précharger les IDs déjà inscrits (O(1) pour les vérifications dans la boucle)
        $existingUserIds = $classe->etudiants()->allRelatedIds()->flip()->toArray();

        $created = 0;

        DB::transaction(function () use ($classe, $rows, &$existingUserIds, &$created) {
            foreach ($rows as ['noDa' => $noDa, 'prenom' => $prenom, 'nom' => $nom, 'statut' => $statut]) {
                $etudiant = $this->createEtudiant->execute($noDa, $prenom, $nom);

                // Ignorer si déjà inscrit dans cette classe ou dans une autre (contrainte unicité)
                if (! isset($existingUserIds[$etudiant->id]) && ! $etudiant->classesInscrites()->exists()) {
                    $classe->etudiants()->attach($etudiant->id, [
                        'statut_cours' => $statut ?: null,
                    ]);
                    // Marquer comme inscrit pour éviter un double attach si le DA apparaît deux fois dans le CSV
                    $existingUserIds[$etudiant->id] = true;
                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * Convertit le contenu en UTF-8 si nécessaire (ex. : export Excel Windows-1252).
     */
    private function normalizeEncoding(string $content): string
    {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }

    /**
     * Parse le contenu CSV et retourne les lignes valides sous forme de tableau associatif.
     *
     * @return array<int, array{noDa: string, prenom: string, nom: string, statut: string}>
     */
    private function parseCsvRows(string $content): array
    {
        $tmp = tmpfile();
        fwrite($tmp, $content);
        rewind($tmp);

        fgetcsv($tmp, 0, ';'); // ignorer l'entête

        $rows = [];

        while (($row = fgetcsv($tmp, 0, ';')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            [$noDa, $nom, $prenom, $statut] = $row;

            // Retirer le BOM UTF-8 éventuel sur le premier champ
            $noDa = ltrim(trim($noDa), "\xEF\xBB\xBF");
            $nom = trim($nom);
            $prenom = trim($prenom);
            $statut = trim($statut);

            if (empty($noDa) || empty($nom) || empty($prenom)) {
                continue;
            }

            $rows[] = compact('noDa', 'nom', 'prenom', 'statut');
        }

        fclose($tmp);

        return $rows;
    }
}
