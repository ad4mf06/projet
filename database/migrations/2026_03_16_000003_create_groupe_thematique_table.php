<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupe_thematique', function (Blueprint $table) {
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnDelete();
            $table->foreignId('thematique_id')->constrained('thematiques')->cascadeOnDelete();
            $table->unique(['groupe_id', 'thematique_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupe_thematique');
    }
};
