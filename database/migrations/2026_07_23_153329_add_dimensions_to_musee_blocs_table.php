<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            // Dimensions personnalisées optionnelles (null = taille automatique)
            $table->unsignedSmallInteger('hauteur_px')->nullable()->after('colonne');
            $table->unsignedSmallInteger('largeur_pct')->nullable()->after('hauteur_px');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('musee_blocs', function (Blueprint $table) {
            $table->dropColumn(['hauteur_px', 'largeur_pct']);
        });
    }
};
