<?php

namespace App\Actions;

use RuntimeException;

/**
 * Extrait un segment [debut, fin] d'une vidéo source par copie de flux.
 *
 * Utilise `-c copy` pour éviter tout réencodage — quasi-instantané
 * quelle que soit la durée de la vidéo. Les coupures se font au keyframe
 * le plus proche, ce qui est suffisant pour un éditeur pédagogique.
 */
class ExtraireSegment
{
    /**
     * Extrait le segment [debut, fin] et l'écrit dans dest sans réencodage.
     *
     * @param  string  $source  Chemin absolu du fichier source.
     * @param  float  $debut  Début du segment en secondes.
     * @param  float  $fin  Fin du segment en secondes.
     * @param  string  $dest  Chemin absolu du fichier de destination.
     *
     * @throws RuntimeException si FFmpeg retourne un code non-zéro.
     */
    public function execute(string $source, float $debut, float $fin, string $dest): void
    {
        $ffmpegBin = config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg');
        $duree = $fin - $debut;

        // -ss avant -i = seek direct (rapide).
        // -t = durée à copier depuis le point de seek.
        // -avoid_negative_ts make_zero = normalise les timestamps à 0
        //   pour que le concat demuxer (ConcateneSegments) accepte le fichier.
        $cmd = sprintf(
            '%s -ss %s -i %s -t %s -c copy -avoid_negative_ts make_zero %s -y 2>&1',
            escapeshellarg($ffmpegBin),
            $debut,
            escapeshellarg($source),
            $duree,
            escapeshellarg($dest),
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Échec de l'extraction FFmpeg ({$debut}s → {$fin}s) : ".implode("\n", $output)
            );
        }
    }
}
