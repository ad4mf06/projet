<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `musee_images` qui stocke les images uploadées pour
     * les projets musée, avec leurs données de recadrage optionnelles.
     */
    public function up(): void
    {
        Schema::create('musee_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_recherche_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt', 255)->nullable();
            $table->string('legende', 500)->nullable();
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('taille'); // en octets
            $table->json('crop_data')->nullable(); // { x, y, width, height, ratio }
            $table->timestamps();

            $table->index('projet_recherche_id');
        });
    }

    /**
     * Supprime la table `musee_images`.
     */
    public function down(): void
    {
        Schema::dropIfExists('musee_images');
    }
};
