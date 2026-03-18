<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute la valeur 'audio' à l'enum type de la table groupe_medias.
     *
     * On utilise DB::statement car Laravel/Doctrine ne supporte pas
     * la modification d'un enum via ->change() de façon fiable sur MySQL.
     * SQLite (utilisé en tests) n'a pas de contrainte enum — on saute la commande.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE groupe_medias MODIFY COLUMN type ENUM('photo', 'document', 'audio') NOT NULL");
        }
    }

    /**
     * Retire la valeur 'audio' de l'enum (retour à l'état initial).
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE groupe_medias MODIFY COLUMN type ENUM('photo', 'document') NOT NULL");
        }
    }
};
