<?php

namespace App\Providers;

use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Observers\ProjetRechercheObserver;
use App\Observers\TypeProjetObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerObservers();
    }

    /**
     * Enregistre les observers Eloquent de l'application.
     */
    protected function registerObservers(): void
    {
        TypeProjet::observe(TypeProjetObserver::class);
        ProjetRecherche::observe(ProjetRechercheObserver::class);
    }

    /**
     * Configure les comportements par défaut de l'application.
     * Règles mot de passe : minimum 8 caractères avec au moins un chiffre.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8)->numbers());
    }
}
