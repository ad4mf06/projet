<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remplace les colonnes `periode_id` et `region_id` de `musee_meta`
     * (qui pointaient vers les tables per-cours `musee_periodes` / `musee_regions`)
     * par `epoque_historique_id` et `region_administrative_id` qui pointent
     * vers les nouveaux référentiels globaux québécois.
     *
     * Les données existantes dans ces colonnes sont perdues (nulles après migration)
     * car il est impossible de mapper automatiquement les valeurs per-cours
     * vers les nouvelles entrées globales standardisées.
     */
    public function up(): void
    {
        Schema::table('musee_meta', function (Blueprint $table): void {
            // Supprimer les anciennes FK per-cours
            $table->dropForeign(['periode_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['periode_id', 'region_id']);

            // Ajouter les nouvelles FK vers les référentiels globaux
            $table->foreignId('epoque_historique_id')
                ->nullable()
                ->after('thematique_id')
                ->constrained('epoques_historiques')
                ->nullOnDelete();

            $table->foreignId('region_administrative_id')
                ->nullable()
                ->after('epoque_historique_id')
                ->constrained('regions_administratives')
                ->nullOnDelete();
        });
    }

    /**
     * Restaure les colonnes `periode_id` et `region_id` originales.
     */
    public function down(): void
    {
        Schema::table('musee_meta', function (Blueprint $table): void {
            $table->dropForeign(['epoque_historique_id']);
            $table->dropForeign(['region_administrative_id']);
            $table->dropColumn(['epoque_historique_id', 'region_administrative_id']);

            $table->foreignId('periode_id')
                ->nullable()
                ->after('thematique_id')
                ->constrained('musee_periodes')
                ->nullOnDelete();

            $table->foreignId('region_id')
                ->nullable()
                ->after('periode_id')
                ->constrained('musee_regions')
                ->nullOnDelete();
        });
    }
};
