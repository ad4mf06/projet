<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes de workflow d'approbation à la table musee_publications.
     *
     * Le statut suit ce cycle : brouillon → soumis → approuve / rejete.
     * Un rejet ramène à brouillon (modification puis nouvelle soumission possible).
     */
    public function up(): void
    {
        Schema::table('musee_publications', function (Blueprint $table) {
            $table->string('statut', 20)->default('brouillon')->after('est_publie');
            $table->timestamp('soumis_le')->nullable()->after('publie_par');
            $table->text('raison_rejet')->nullable()->after('soumis_le');
        });
    }

    /**
     * Retire les colonnes de workflow d'approbation.
     */
    public function down(): void
    {
        Schema::table('musee_publications', function (Blueprint $table) {
            $table->dropColumn(['statut', 'soumis_le', 'raison_rejet']);
        });
    }
};
