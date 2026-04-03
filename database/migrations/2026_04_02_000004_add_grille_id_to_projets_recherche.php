<?php

use App\Models\GrilleCorrection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table): void {
            // null = grille legacy (ProjetNote::CRITERES) ; renseigné = grille personnalisée
            $table->foreignId('grille_id')
                ->nullable()
                ->after('groupe_id')
                ->constrained('grilles_correction')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table): void {
            $table->dropForeignIdFor(GrilleCorrection::class);
            $table->dropColumn('grille_id');
        });
    }
};
