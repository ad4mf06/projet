<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute le champ de position de l'image de fond en-tête.
     *
     * Permet de choisir où la photo est "ancrée" quand elle est rognée
     * par le conteneur CSS : center (défaut), top ou bottom.
     */
    public function up(): void
    {
        Schema::table('musee_meta', function (Blueprint $table) {
            $table->string('entete_image_position')->default('center')->after('entete_overlay_couleur');
        });
    }

    public function down(): void
    {
        Schema::table('musee_meta', function (Blueprint $table) {
            $table->dropColumn('entete_image_position');
        });
    }
};
