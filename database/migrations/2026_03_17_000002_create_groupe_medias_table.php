<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupe_medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->enum('type', ['photo', 'document']);
            $table->unsignedBigInteger('taille'); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupe_medias');
    }
};
