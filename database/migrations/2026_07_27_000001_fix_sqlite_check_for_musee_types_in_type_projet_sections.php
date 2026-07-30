<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Étend la contrainte CHECK de SQLite pour inclure les types musée.
     *
     * SQLite stocke les contraintes CHECK directement dans le DDL de la table
     * (sqlite_master). Les migrations précédentes n'avaient mis à jour que MySQL
     * (via ALTER TABLE … MODIFY ENUM) en supposant que SQLite ne valide pas les
     * ENUMs — mais la table a été créée avec un CHECK explicite. On le met à jour
     * via PRAGMA writable_schema en manipulant la chaîne DDL en PHP.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $this->replaceCheckConstraint(
            old: "check (\"type\" in ('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio', 'choix_questions', 'tache', 'schema_visuel'))",
            new: "check (\"type\" in ('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio', 'choix_questions', 'tache', 'schema_visuel', 'musee_entete', 'musee_intro', 'musee_contenu'))",
        );
    }

    /**
     * Retire les types musée de la contrainte CHECK SQLite.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $this->replaceCheckConstraint(
            old: "check (\"type\" in ('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio', 'choix_questions', 'tache', 'schema_visuel', 'musee_entete', 'musee_intro', 'musee_contenu'))",
            new: "check (\"type\" in ('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio', 'choix_questions', 'tache', 'schema_visuel'))",
        );
    }

    /**
     * Remplace la contrainte CHECK dans le DDL stocké dans sqlite_master.
     */
    private function replaceCheckConstraint(string $old, string $new): void
    {
        $row = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'type_projet_sections'",
        );

        if (! $row || ! str_contains($row->sql, $old)) {
            // Contrainte introuvable — probablement déjà mise à jour ou schema différent.
            return;
        }

        $updatedSql = str_replace($old, $new, $row->sql);

        DB::statement('PRAGMA writable_schema = ON');
        DB::statement(
            "UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'type_projet_sections'",
            [$updatedSql],
        );
        DB::statement('PRAGMA writable_schema = OFF');
        DB::statement('VACUUM');
    }
};
