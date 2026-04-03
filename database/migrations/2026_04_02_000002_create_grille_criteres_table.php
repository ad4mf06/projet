<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grille_criteres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grille_id')->constrained('grilles_correction')->cascadeOnDelete();
            $table->string('label', 255);
            // Pondération en points (doit être > 0 ; la somme sur la grille devrait totaliser 100)
            $table->unsignedSmallInteger('ponderation');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grille_criteres');
    }
};
