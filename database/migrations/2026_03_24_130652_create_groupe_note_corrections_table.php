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
        Schema::create('groupe_note_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('groupe_notes')->cascadeOnDelete();
            $table->string('commentaire_id', 36); // UUID de la marque TipTap
            $table->text('contenu');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['note_id', 'commentaire_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groupe_note_corrections');
    }
};
