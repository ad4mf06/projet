<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('type', 10); // pdf, doc, docx
            $table->unsignedBigInteger('taille'); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_documents');
    }
};
