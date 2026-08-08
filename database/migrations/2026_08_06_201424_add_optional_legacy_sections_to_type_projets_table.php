<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les drapeaux contrôlant les sections legacy (intro + conclusion individuelle).
     *
     * DEFAULT true pour préserver le comportement des TypeProjets existants
     * qui utilisaient déjà ces sections de repli. Les nouveaux TypeProjets
     * démarreront avec false (géré au niveau du formulaire).
     */
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->boolean('has_introduction')->default(true)->after('aide_reference');
            $table->boolean('has_conclusion_individuelle')->default(true)->after('has_introduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->dropColumn(['has_introduction', 'has_conclusion_individuelle']);
        });
    }
};
