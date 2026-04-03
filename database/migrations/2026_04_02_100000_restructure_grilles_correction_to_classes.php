<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer grille_id des projets de recherche (FK + colonne)
        Schema::table('projets_recherche', function (Blueprint $table): void {
            $table->dropForeign(['grille_id']);
            $table->dropColumn('grille_id');
        });

        // Restructurer grilles_correction : enseignant_id → classe_id (unique)
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->dropForeign(['enseignant_id']);
            $table->dropColumn('enseignant_id');
            $table->foreignId('classe_id')
                ->after('id')
                ->unique()
                ->constrained('classes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->dropForeign(['classe_id']);
            $table->dropUnique(['classe_id']);
            $table->dropColumn('classe_id');
            $table->foreignId('enseignant_id')
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        Schema::table('projets_recherche', function (Blueprint $table): void {
            $table->foreignId('grille_id')
                ->nullable()
                ->constrained('grilles_correction')
                ->nullOnDelete();
        });
    }
};
