<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table): void {
            // Nombre de paragraphes de développement actifs (1 au minimum, 5 au maximum)
            $table->unsignedTinyInteger('dev_count')->default(1)->after('groupe_id');
        });
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table): void {
            $table->dropColumn('dev_count');
        });
    }
};
