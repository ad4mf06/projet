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
        Schema::create('echeancier_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('semaine'); // 1–15
            $table->text('etape');
            $table->boolean('is_done')->default(false);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeancier_etapes');
    }
};
