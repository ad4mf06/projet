<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des paragraphes de développement d'un projet de recherche.
     */
    public function up(): void
    {
        Schema::create('projet_developpements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')
                ->constrained('projets_recherche')
                ->onDelete('cascade');
            $table->unsignedInteger('ordre')->default(1);
            $table->string('titre')->nullable();
            $table->longText('contenu')->nullable();
            $table->timestamps();

            $table->index(['projet_id', 'ordre']);
        });
    }

    /**
     * Supprime la table des paragraphes de développement.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_developpements');
    }
};
