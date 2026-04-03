<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_grille_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Cascade : si le critère est supprimé de la grille, la note disparaît
            $table->foreignId('critere_id')->constrained('grille_criteres')->cascadeOnDelete();
            // Valeurs possibles : 0 (mauvais), 2 (passable), 3 (bon), 4 (excellent)
            $table->unsignedTinyInteger('note');
            $table->timestamps();

            $table->unique(['projet_id', 'user_id', 'critere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_grille_notes');
    }
};
