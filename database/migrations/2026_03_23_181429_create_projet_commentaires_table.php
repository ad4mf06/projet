<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_commentaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            // Identifiant du champ commenté (ex: 'introduction_amener', 'dev_2_contenu', 'conclusion_42')
            $table->string('champ', 80);
            $table->text('contenu');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['projet_id', 'champ']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_commentaires');
    }
};
