<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_periodes` qui stocke les périodes historiques
     * définies par l'enseignant pour catégoriser les projets musée.
     */
    public function up(): void
    {
        Schema::create('musee_periodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table `musee_periodes`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_periodes');
    }
};
