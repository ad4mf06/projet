<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne JSON musee_contraintes à type_projet_sections.
     *
     * Cette colonne stocke la liste des blocs attendus par l'enseignant pour
     * chaque section d'un musée virtuel (type, requis/optionnel, position).
     * Structure : [{ "type": "texte|image|carrousel|video", "requis": bool, "label": string }]
     */
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table): void {
            $table->json('musee_contraintes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table): void {
            $table->dropColumn('musee_contraintes');
        });
    }
};
