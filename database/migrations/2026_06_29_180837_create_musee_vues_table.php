<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_vues` pour le suivi des visites de la galerie publique.
     *
     * Les IP sont hashées pour respecter la vie privée tout en permettant
     * le dédoublonnage sur 24h (une visite unique par IP par projet par jour).
     */
    public function up(): void
    {
        Schema::create('musee_vues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_recherche_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->string('ip_hash', 64); // SHA-256 de l'IP
            $table->timestamp('vue_le')->useCurrent();

            $table->index(['projet_recherche_id', 'vue_le']);
            // Index composé pour le dédoublonnage rapide (même IP + même projet + même jour)
            $table->index(['projet_recherche_id', 'ip_hash']);
        });
    }

    /**
     * Supprime la table `musee_vues`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_vues');
    }
};
