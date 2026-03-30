<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supprime les colonnes dev_N_* et dev_count de projets_recherche
     * maintenant que les données sont dans projet_developpements.
     */
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn([
                'dev_count',
                'dev_1_titre', 'dev_1_contenu',
                'dev_2_titre', 'dev_2_contenu',
                'dev_3_titre', 'dev_3_contenu',
                'dev_4_titre', 'dev_4_contenu',
                'dev_5_titre', 'dev_5_contenu',
            ]);
        });
    }

    /**
     * Recrée les colonnes dev_N_* et dev_count pour permettre le rollback.
     */
    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->unsignedTinyInteger('dev_count')->default(1)->after('groupe_id');

            for ($i = 1; $i <= 5; $i++) {
                $table->string("dev_{$i}_titre")->nullable();
                $table->longText("dev_{$i}_contenu")->nullable();
            }
        });
    }
};
