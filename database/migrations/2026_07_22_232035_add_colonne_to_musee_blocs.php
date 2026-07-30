<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne `colonne` aux blocs musée.
     *
     * Indique dans quelle colonne s'affiche le bloc lorsque la section parente
     * est configurée en layout 2 colonnes. Valeur 1 (défaut) ou 2.
     */
    public function up(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            $table->tinyInteger('colonne')->unsigned()->default(1)->after('ordre');
        });
    }

    /**
     * Retire la colonne colonne.
     */
    public function down(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            $table->dropColumn('colonne');
        });
    }
};
