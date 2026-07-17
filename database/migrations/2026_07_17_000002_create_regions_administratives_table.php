<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table `regions_administratives` qui contient les 17 régions
     * administratives officielles du Québec, utilisées comme référentiel
     * global pour catégoriser les projets musée.
     *
     * Contrairement aux anciennes `musee_regions` (par cours), cette table
     * est partagée par tous les cours et non modifiable par les enseignants.
     */
    public function up(): void
    {
        Schema::create('regions_administratives', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150);
            $table->char('code_region', 2);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table `regions_administratives`.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions_administratives');
    }
};
