<?php

namespace App\Actions;

/**
 * Fusionne les segments de transcription de deux vidéos après un jumelage (ProcessVideoMerge).
 *
 * Plutôt que de relancer Whisper sur la vidéo fusionnée, on repositionne
 * les segments existants selon la position d'insertion :
 *   - Segments de la base avant la position : inchangés.
 *   - Segments de la vidéo insérée : décalés de +position.
 *   - Segments de la base après la position : décalés de +dureeInsert.
 */
class FusionnerTranscriptions
{
    /**
     * Produit la liste fusionnée et triée des segments.
     *
     * @param  array<array{start: float, end: float, text: string}>  $segmentsBase  Segments de la vidéo de base.
     * @param  array<array{start: float, end: float, text: string}>  $segmentsInsert  Segments de la vidéo insérée (peut être vide).
     * @param  float  $position  Secondes auxquelles la vidéo est insérée dans la base.
     * @param  float  $dureeInsert  Durée de la vidéo insérée en secondes.
     * @return array<array{start: float, end: float, text: string}>
     */
    public function execute(
        array $segmentsBase,
        array $segmentsInsert,
        float $position,
        float $dureeInsert,
    ): array {
        $result = [];

        // Segments de la base avant le point d'insertion — inchangés.
        foreach ($segmentsBase as $s) {
            if ((float) $s['start'] < $position) {
                $result[] = $s;
            }
        }

        // Segments de la vidéo insérée — décalés de +position.
        foreach ($segmentsInsert as $s) {
            $result[] = [
                'start' => round((float) $s['start'] + $position, 3),
                'end' => round((float) $s['end'] + $position, 3),
                'text' => $s['text'],
            ];
        }

        // Segments de la base après le point d'insertion — décalés de +dureeInsert.
        foreach ($segmentsBase as $s) {
            if ((float) $s['start'] >= $position) {
                $result[] = [
                    'start' => round((float) $s['start'] + $dureeInsert, 3),
                    'end' => round((float) $s['end'] + $dureeInsert, 3),
                    'text' => $s['text'],
                ];
            }
        }

        usort($result, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $result;
    }
}
