<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classe_documents', function (Blueprint $table) {
            $table->renameColumn('chemin', 'file_path');
        });

        Schema::table('groupe_medias', function (Blueprint $table) {
            $table->renameColumn('chemin', 'file_path');
        });
    }

    public function down(): void
    {
        Schema::table('classe_documents', function (Blueprint $table) {
            $table->renameColumn('file_path', 'chemin');
        });

        Schema::table('groupe_medias', function (Blueprint $table) {
            $table->renameColumn('file_path', 'chemin');
        });
    }
};
