<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter no_da à la table users
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_da', 20)->nullable()->after('email');
        });

        // 2. Copier le no_da de classe_etudiant vers users
        DB::table('classe_etudiant')
            ->select('user_id', 'no_da')
            ->get()
            ->each(function ($row) {
                DB::table('users')
                    ->where('id', $row->user_id)
                    ->whereNull('no_da')
                    ->update(['no_da' => $row->no_da]);
            });

        // 3. Supprimer no_da de classe_etudiant
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->dropColumn('no_da');
        });
    }

    public function down(): void
    {
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->string('no_da', 20)->nullable();
        });

        DB::table('users')
            ->whereNotNull('no_da')
            ->each(function ($user) {
                DB::table('classe_etudiant')
                    ->where('user_id', $user->id)
                    ->update(['no_da' => $user->no_da]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('no_da');
        });
    }
};
