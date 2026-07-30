<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne JSON `musee_layout` aux sections de type projet.
     *
     * Structure : { "nb_colonnes": 1|2, "ratio": "50-50"|"60-40"|"40-60"|"70-30"|"30-70" }
     * null = mise en page par défaut (1 colonne pleine largeur).
     */
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->json('musee_layout')->nullable()->after('musee_contraintes');
        });
    }

    /**
     * Retire la colonne musee_layout.
     */
    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->dropColumn('musee_layout');
        });
    }
};
