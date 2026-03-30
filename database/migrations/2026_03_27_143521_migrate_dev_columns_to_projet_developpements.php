<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copie les colonnes dev_N_titre / dev_N_contenu vers projet_developpements
     * et met à jour les champs des annotations et commentaires.
     */
    public function up(): void
    {
        $projets = DB::table('projets_recherche')->get();

        foreach ($projets as $projet) {
            $devCount = $projet->dev_count ?? 5;

            for ($i = 1; $i <= 5; $i++) {
                $titreCol = "dev_{$i}_titre";
                $contenuCol = "dev_{$i}_contenu";

                $titre = $projet->$titreCol ?? null;
                $contenu = $projet->$contenuCol ?? null;

                // N'insérer que les paragraphes ayant du contenu ou dans la limite active
                if ($i > $devCount && empty($titre) && empty($contenu)) {
                    continue;
                }

                $newId = DB::table('projet_developpements')->insertGetId([
                    'projet_id' => $projet->id,
                    'ordre' => $i,
                    'titre' => $titre,
                    'contenu' => $contenu,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Mettre à jour les annotations qui référencent dev_N_contenu
                DB::table('projet_annotations')
                    ->where('projet_id', $projet->id)
                    ->where('champ', "dev_{$i}_contenu")
                    ->update(['champ' => "developpement_{$newId}"]);

                // Mettre à jour les commentaires enseignant qui référencent dev_N_contenu
                DB::table('projet_commentaires')
                    ->where('projet_id', $projet->id)
                    ->where('champ', "dev_{$i}_contenu")
                    ->update(['champ' => "developpement_{$newId}"]);
            }
        }
    }

    /**
     * Recopie les 5 premiers paragraphes de projet_developpements vers les colonnes dev_N_*.
     * Supprime ensuite les enregistrements migrés.
     */
    public function down(): void
    {
        $projets = DB::table('projets_recherche')->get();

        foreach ($projets as $projet) {
            $developpements = DB::table('projet_developpements')
                ->where('projet_id', $projet->id)
                ->orderBy('ordre')
                ->limit(5)
                ->get();

            $updates = [];
            foreach ($developpements as $dev) {
                $i = $dev->ordre;
                $updates["dev_{$i}_titre"] = $dev->titre;
                $updates["dev_{$i}_contenu"] = $dev->contenu;

                // Rétablir les champs annotations / commentaires
                DB::table('projet_annotations')
                    ->where('projet_id', $projet->id)
                    ->where('champ', "developpement_{$dev->id}")
                    ->update(['champ' => "dev_{$i}_contenu"]);

                DB::table('projet_commentaires')
                    ->where('projet_id', $projet->id)
                    ->where('champ', "developpement_{$dev->id}")
                    ->update(['champ' => "dev_{$i}_contenu"]);
            }

            if (! empty($updates)) {
                DB::table('projets_recherche')
                    ->where('id', $projet->id)
                    ->update($updates);
            }
        }

        DB::table('projet_developpements')->truncate();
    }
};
