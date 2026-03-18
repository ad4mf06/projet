<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_etudiant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('no_da');
            $table->string('statut_cours')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_etudiant');
    }
};
