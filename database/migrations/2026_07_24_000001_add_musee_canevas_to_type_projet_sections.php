<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne JSON `musee_canevas` aux sections de type projet.
     *
     * Cette colonne remplace `musee_layout` et `musee_contraintes` en un seul
     * document structuré décrivant le canevas de zones dessinées par l'enseignant.
     *
     * Structure :
     * {
     *   "hauteur_vw": 56,
     *   "zones": [
     *     { "id": "z1", "type": "video",  "label": "...", "x": 0,  "y": 0,  "w": 55, "h": 60, "ordre_mobile": 1 },
     *     { "id": "z2", "type": "texte",  "label": "...", "x": 55, "y": 0,  "w": 45, "h": 35, "ordre_mobile": 2 },
     *     { "id": "z3", "type": "image",  "label": "...", "x": 55, "y": 35, "w": 45, "h": 25, "ordre_mobile": 3 }
     *   ]
     * }
     *
     * Les valeurs x, y, w, h sont en pourcentage du conteneur.
     * null = aucun canevas défini (mode blocs libre, rétrocompatible).
     */
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->json('musee_canevas')->nullable()->after('musee_layout');
        });
    }

    /**
     * Retire la colonne musee_canevas.
     */
    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->dropColumn('musee_canevas');
        });
    }
};
