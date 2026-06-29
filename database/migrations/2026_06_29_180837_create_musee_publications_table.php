<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_publications` qui suit l'état de publication
     * d'un projet musée : publication directe de l'original ou d'une copie
     * modifiée par l'enseignant après correction.
     */
    public function up(): void
    {
        Schema::create('musee_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_recherche_id')->unique()->constrained('projets_recherche')->cascadeOnDelete();
            $table->boolean('est_publie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();

            // Indique si ce projet est une copie faite par le prof pour modification avant publication.
            // Le projet original (étudiant) reste intact.
            $table->boolean('est_copie_prof')->default(false);
            $table->foreignId('projet_original_id')->nullable()->constrained('projets_recherche')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Supprime la table `musee_publications`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_publications');
    }
};
