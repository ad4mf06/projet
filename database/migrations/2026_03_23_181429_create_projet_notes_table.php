<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            // null = note pour l'ensemble du groupe ; renseigné = note pour la conclusion individuelle d'un étudiant
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Clé du critère de la grille de correction (ex: 'normes_presentation', 'developpement_faits')
            $table->string('critere', 60);
            // Valeurs possibles : 0 (mauvais), 2 (passable), 3 (bon), 4 (excellent)
            $table->unsignedTinyInteger('note');
            $table->timestamps();

            $table->unique(['projet_id', 'user_id', 'critere']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_notes');
    }
};
