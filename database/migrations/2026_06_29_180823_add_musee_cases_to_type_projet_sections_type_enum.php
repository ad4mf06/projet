<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Étend l'enum `type` de `type_projet_sections` avec les valeurs musée virtuel.
     *
     * Les trois nouveaux types (musee_entete, musee_intro, musee_contenu) permettent
     * de définir des sections spécifiques au format site web du musée virtuel.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM(
                'texte', 'paragraphes', 'individuel', 'entrevue',
                'video', 'audio', 'choix_questions', 'tache', 'schema_visuel',
                'musee_entete', 'musee_intro', 'musee_contenu'
            ) NOT NULL DEFAULT 'texte'");
        }
        // SQLite ne valide pas les ENUMs (stockés en TEXT) — aucune modification nécessaire.
    }

    /**
     * Retire les valeurs musée de l'enum.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM(
                'texte', 'paragraphes', 'individuel', 'entrevue',
                'video', 'audio', 'choix_questions', 'tache', 'schema_visuel'
            ) NOT NULL DEFAULT 'texte'");
        }
        // SQLite : no-op
    }
};
