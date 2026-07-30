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
        Schema::table('type_projet_criteres', function (Blueprint $table) {
            // Marque le critère auto-généré pour la publication du musée.
            // Quand vrai, ce critère est automatiquement coché à l'approbation
            // du musée par l'enseignant et décoché en cas de rejet.
            $table->boolean('is_musee_publication')->default(false)->after('visible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_projet_criteres', function (Blueprint $table) {
            $table->dropColumn('is_musee_publication');
        });
    }
};
