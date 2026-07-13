<?php

namespace App\Console\Commands;

use App\Models\GroupeVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetVideosBloquees extends Command
{
    /**
     * @var string
     */
    protected $signature = 'videos:reset-bloquees
                            {--heures=2 : Nombre d\'heures avant qu\'une vidéo soit considérée bloquée}
                            {--dry-run : Affiche les vidéos concernées sans les modifier}';

    /**
     * @var string
     */
    protected $description = 'Réinitialise à "erreur" les vidéos bloquées en traitement depuis trop longtemps.';

    /**
     * Détecte et corrige les vidéos dont le traitement ou la transcription est bloqué.
     *
     * Une vidéo est considérée bloquée si son traitement_statut ou transcription_statut
     * est 'en_attente' ou 'en_cours' et qu'elle n'a pas été mise à jour
     * depuis le nombre d'heures configuré.
     */
    public function handle(): int
    {
        $heures = (int) $this->option('heures');
        $dryRun = (bool) $this->option('dry-run');
        $seuil = now()->subHours($heures);

        $videosTraitement = GroupeVideo::query()
            ->whereIn('traitement_statut', [
                GroupeVideo::TRAITEMENT_EN_ATTENTE,
                GroupeVideo::TRAITEMENT_EN_COURS,
            ])
            ->where('updated_at', '<', $seuil)
            ->get();

        $videosTranscription = GroupeVideo::query()
            ->whereIn('transcription_statut', [
                GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
                GroupeVideo::TRANSCRIPTION_EN_COURS,
            ])
            ->where('updated_at', '<', $seuil)
            ->get();

        if ($videosTraitement->isEmpty() && $videosTranscription->isEmpty()) {
            $this->info('Aucune vidéo bloquée trouvée.');

            return self::SUCCESS;
        }

        if ($videosTraitement->isNotEmpty()) {
            $this->line('Traitement bloqué :');
            $this->table(
                ['ID', 'Titre', 'Statut traitement', 'Bloquée depuis'],
                $videosTraitement->map(fn ($v) => [
                    $v->id,
                    $v->titre,
                    $v->traitement_statut,
                    $v->updated_at->diffForHumans(),
                ])
            );
        }

        if ($videosTranscription->isNotEmpty()) {
            $this->line('Transcription bloquée :');
            $this->table(
                ['ID', 'Titre', 'Statut transcription', 'Bloquée depuis'],
                $videosTranscription->map(fn ($v) => [
                    $v->id,
                    $v->titre,
                    $v->transcription_statut,
                    $v->updated_at->diffForHumans(),
                ])
            );
        }

        if ($dryRun) {
            $this->warn('[dry-run] Aucune modification effectuée.');

            return self::SUCCESS;
        }

        foreach ($videosTraitement as $video) {
            $video->update(['traitement_statut' => GroupeVideo::TRAITEMENT_ERREUR]);

            // Supprimer les jobs en attente liés à cette vidéo pour
            // éviter qu'ils ne s'exécutent plus tard et écrasent le statut.
            DB::table('jobs')
                ->where('payload', 'like', "%\"id\";i:{$video->id};%")
                ->delete();

            Log::warning('Traitement vidéo bloqué réinitialisé automatiquement', [
                'video_id' => $video->id,
                'titre' => $video->titre,
                'statut_precedent' => $video->getOriginal('traitement_statut'),
                'bloquee_depuis' => $video->updated_at->toDateTimeString(),
            ]);
        }

        foreach ($videosTranscription as $video) {
            $video->update(['transcription_statut' => GroupeVideo::TRANSCRIPTION_ERREUR]);

            Log::warning('Transcription vidéo bloquée réinitialisée automatiquement', [
                'video_id' => $video->id,
                'titre' => $video->titre,
                'statut_precedent' => $video->getOriginal('transcription_statut'),
                'bloquee_depuis' => $video->updated_at->toDateTimeString(),
            ]);
        }

        $countTraitement = $videosTraitement->count();
        $countTranscription = $videosTranscription->count();

        if ($countTraitement > 0) {
            $this->info("{$countTraitement} traitement(s) réinitialisé(s) à \"erreur\".");
        }

        if ($countTranscription > 0) {
            $this->info("{$countTranscription} transcription(s) réinitialisée(s) à \"erreur\".");
        }

        return self::SUCCESS;
    }
}
