<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des projets de recherche partagés par groupe.
     * Un seul projet par groupe — les conclusions individuelles sont dans projet_conclusions.
     */
    public function up(): void
    {
        Schema::create('projets_recherche', function (Blueprint $table) {
            $table->id();

            $table->foreignId('groupe_id')
                ->constrained('groupes')
                ->cascadeOnDelete();

            // Page titre
            $table->string('titre_projet')->nullable();

            // Introduction (amener / poser / diviser)
            $table->text('introduction_amener')->nullable();
            $table->text('introduction_poser')->nullable();
            $table->text('introduction_diviser')->nullable();

            // 5 paragraphes de développement
            $table->string('dev_1_titre')->nullable();
            $table->text('dev_1_contenu')->nullable();

            $table->string('dev_2_titre')->nullable();
            $table->text('dev_2_contenu')->nullable();

            $table->string('dev_3_titre')->nullable();
            $table->text('dev_3_contenu')->nullable();

            $table->string('dev_4_titre')->nullable();
            $table->text('dev_4_contenu')->nullable();

            $table->string('dev_5_titre')->nullable();
            $table->text('dev_5_contenu')->nullable();

            $table->timestamps();

            // Un seul projet par groupe
            $table->unique('groupe_id');
        });
    }

    /**
     * Supprime la table des projets de recherche.
     */
    public function down(): void
    {
        Schema::dropIfExists('projets_recherche');
    }
};
