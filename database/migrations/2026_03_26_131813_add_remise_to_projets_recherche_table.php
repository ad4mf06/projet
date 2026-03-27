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
        Schema::table('projets_recherche', function (Blueprint $table) {
            // Date limite fixée par l'enseignant
            $table->timestamp('date_remise')->nullable()->after('verrouille');
            // Horodatage de la remise du travail par l'équipe
            $table->timestamp('remis_le')->nullable()->after('date_remise');
            // Si true, l'équipe peut remettre plusieurs fois
            $table->boolean('remises_multiples')->default(false)->after('remis_le');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn(['date_remise', 'remis_le', 'remises_multiples']);
        });
    }
};
