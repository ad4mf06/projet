<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_meta` qui stocke les métadonnées d'un projet musée :
     * catégorisation (période, thème, région), contenu de la carte galerie
     * et informations de l'en-tête visuelle.
     */
    public function up(): void
    {
        Schema::create('musee_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_recherche_id')->unique()->constrained('projets_recherche')->cascadeOnDelete();

            // Catégorisation pour les filtres de la galerie
            $table->foreignId('periode_id')->nullable()->constrained('musee_periodes')->nullOnDelete();
            $table->foreignId('thematique_id')->nullable()->constrained('thematiques')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('musee_regions')->nullOnDelete();

            // Carte galerie (aperçu dans la liste publique)
            $table->string('slug')->unique();
            $table->text('intro_texte')->nullable();
            $table->string('intro_image_path')->nullable();

            // En-tête visuelle du site étudiant
            $table->string('entete_image_path')->nullable();
            $table->string('entete_titre', 255)->nullable();
            $table->string('entete_sous_titre', 255)->nullable();
            $table->string('entete_overlay_couleur', 20)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Supprime la table `musee_meta`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_meta');
    }
};
