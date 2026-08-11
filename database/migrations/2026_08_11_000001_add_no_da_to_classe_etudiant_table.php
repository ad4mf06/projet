<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute no_da à classe_etudiant pour les bases créées avant son ajout.
     *
     * Idempotente : ne fait rien si la colonne existe déjà.
     * Remplit les lignes existantes depuis users.no_da.
     */
    public function up(): void
    {
        if (Schema::hasColumn('classe_etudiant', 'no_da')) {
            return;
        }

        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->string('no_da')->nullable()->after('user_id');
        });

        // Remplir les lignes existantes depuis la table users
        DB::statement(
            'UPDATE classe_etudiant SET no_da = (SELECT no_da FROM users WHERE users.id = classe_etudiant.user_id)'
        );
    }

    public function down(): void
    {
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->dropColumn('no_da');
        });
    }
};
