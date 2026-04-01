<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute position (ordre séquentiel dans le champ) et mot_annote (texte surligné)
     * à la table projet_annotations pour un tri stable et un nettoyage des orphelines.
     */
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->nullable()->after('type');
            $table->text('mot_annote')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropColumn(['position', 'mot_annote']);
        });
    }
};
