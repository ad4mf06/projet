<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des conclusions individuelles par étudiant pour un projet de groupe.
     */
    public function up(): void
    {
        Schema::create('projet_conclusions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('projet_id')
                ->constrained('projets_recherche')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('contenu')->nullable();

            $table->timestamps();

            // Une conclusion par étudiant par projet
            $table->unique(['projet_id', 'user_id']);
        });
    }

    /**
     * Supprime la table des conclusions individuelles.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_conclusions');
    }
};
