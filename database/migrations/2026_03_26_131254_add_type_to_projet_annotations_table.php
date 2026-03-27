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
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->enum('type', ['commentaire', 'correction'])
                ->default('commentaire')
                ->after('contenu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
