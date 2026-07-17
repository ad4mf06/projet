<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `epoques_historiques` qui contient les grandes périodes
     * de l'histoire du Québec, utilisées comme référentiel global pour
     * catégoriser les projets musée.
     *
     * Contrairement aux anciennes `musee_periodes` (par cours), cette table
     * est partagée par tous les cours et non modifiable par les enseignants.
     */
    public function up(): void
    {
        Schema::create('epoques_historiques', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150);
            $table->smallInteger('annee_debut')->nullable();
            $table->smallInteger('annee_fin')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table `epoques_historiques`.
     */
    public function down(): void
    {
        Schema::dropIfExists('epoques_historiques');
    }
};
