<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne `type` à `types_projets` pour distinguer
     * les projets standard des projets musée virtuel.
     */
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table): void {
            $table->string('type', 20)->default('standard')->after('nom');
        });

        // Les projets existants sont tous de type standard.
        DB::table('types_projets')->update(['type' => 'standard']);
    }

    /**
     * Retire la colonne `type` de `types_projets`.
     */
    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
