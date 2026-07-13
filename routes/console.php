<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Réinitialise toutes les heures les vidéos dont le traitement est bloqué
// depuis plus de 2 heures, pour éviter un polling infini côté frontend.
Schedule::command('videos:reset-bloquees')->hourly();
