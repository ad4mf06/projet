<?php

namespace App\Actions;

/**
 * Ajuste les segments de transcription existants après un trim/coupes FFmpeg.
 *
 * Plutôt que de relancer Whisper après chaque modification vidéo (ProcessVideoEdit),
 * on recalcule les timestamps des segments en fonction des plages conservées.
 * L'opération est quasi-instantanée et préserve les corrections manuelles.
 */
class AjusterTranscription
{
    /**
     * Recalcule les timestamps des segments selon les plages conservées.
     *
     * @param  array<array{start: float, end: float, text: string}>  $segments  Segments originaux.
     * @param  float  $debut  Début de la plage conservée (secondes).
     * @param  float  $fin  Fin de la plage conservée (secondes).
     * @param  array<array{debut: float, fin: float}>  $coupes  Segments internes supprimés.
     * @return array<array{start: float, end: float, text: string}>|null  Segments ajustés, ou null si aucun ne survit.
     */
    public function execute(array $segments, float $debut, float $fin, array $coupes): ?array
    {
        $plages = $this->calculerPlages($debut, $fin, $coupes);
        $result = [];

        foreach ($segments as $segment) {
            $segStart = (float) $segment['start'];
            $segEnd = (float) $segment['end'];

            foreach ($plages as $i => [$plageDebut, $plageFin]) {
                // Le segment ne chevauche pas cette plage.
                if ($segEnd <= $plageDebut || $segStart >= $plageFin) {
                    continue;
                }

                // Calcul du décalage temporel : somme des durées des plages précédentes.
                $offsetSortie = 0.0;
                for ($j = 0; $j < $i; $j++) {
                    $offsetSortie += $plages[$j][1] - $plages[$j][0];
                }

                // Recadrage du segment dans les bornes de la plage.
                $startRecadre = max($segStart, $plageDebut);
                $endRecadre = min($segEnd, $plageFin);

                $result[] = [
                    'start' => round($offsetSortie + ($startRecadre - $plageDebut), 3),
                    'end'   => round($offsetSortie + ($endRecadre - $plageDebut), 3),
                    'text'  => $segment['text'],
                ];
            }
        }

        return empty($result) ? null : $result;
    }

    /**
     * Calcule les plages [debut, fin] conservées après suppression des coupes.
     *
     * @param  array<array{debut: float, fin: float}>  $coupes
     * @return array<array{0: float, 1: float}>
     */
    private function calculerPlages(float $debut, float $fin, array $coupes): array
    {
        if (empty($coupes)) {
            return [[$debut, $fin]];
        }

        usort($coupes, fn ($a, $b) => $a['debut'] <=> $b['debut']);

        $plages = [];
        $curseur = $debut;

        foreach ($coupes as $coupe) {
            if ($coupe['debut'] > $curseur) {
                $plages[] = [$curseur, (float) $coupe['debut']];
            }
            $curseur = (float) $coupe['fin'];
        }

        if ($curseur < $fin) {
            $plages[] = [$curseur, $fin];
        }

        return $plages;
    }
}
