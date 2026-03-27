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
            $table->boolean('correction_visible')->default(false)->after('dev_5_contenu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn('correction_visible');
        });
    }
};
