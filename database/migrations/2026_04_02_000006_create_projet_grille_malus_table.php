<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_grille_malus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Cascade : si le malus est supprimé de la grille, l'enregistrement disparaît
            $table->foreignId('malus_id')->constrained('grille_malus')->cascadeOnDelete();
            $table->boolean('applique')->default(false);
            $table->timestamps();

            $table->unique(['projet_id', 'user_id', 'malus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_grille_malus');
    }
};
