<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('echeancier_etudiant_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('echeancier_etape_id')
                ->constrained('echeancier_etapes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            // Un étudiant ne peut avoir qu'une entrée de progression par étape
            $table->unique(['echeancier_etape_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeancier_etudiant_progress');
    }
};
