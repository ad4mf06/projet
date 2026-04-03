<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grille_malus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grille_id')->constrained('grilles_correction')->cascadeOnDelete();
            // Ex : « Fautes de français »
            $table->string('label', 255);
            // Nombre de points à déduire (valeur positive — la soustraction est appliquée à la note finale)
            $table->decimal('deduction', 5, 2)->unsigned();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grille_malus');
    }
};
