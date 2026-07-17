<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('musee_video_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bloc_id')->constrained('musee_blocs')->cascadeOnDelete();
            // section_id pointe vers une section du même type projet (pas forcément celle du bloc)
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->unsignedInteger('debut_secondes');
            $table->unsignedInteger('fin_secondes');
            $table->string('label', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musee_video_segments');
    }
};
