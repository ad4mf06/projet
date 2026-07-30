<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne `zone_id` aux blocs du musée.
     *
     * Référence l'identifiant d'une zone définie dans le champ `musee_canevas`
     * de la section parente (TypeProjetSection). Un bloc lié à une zone
     * remplace le contenu libre positionné par colonne/ordre.
     *
     * null = bloc en mode libre (rétrocompatible avec l'ancien système).
     */
    public function up(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            // UUID court correspondant au champ "id" dans musee_canevas.zones
            $table->string('zone_id', 36)->nullable()->after('section_id');
        });
    }

    /**
     * Retire la colonne zone_id.
     */
    public function down(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            $table->dropColumn('zone_id');
        });
    }
};
