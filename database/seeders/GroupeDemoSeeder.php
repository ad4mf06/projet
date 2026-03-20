<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GroupeDemoSeeder extends Seeder
{
    /**
     * Crée un scénario de démonstration complet pour tester les groupes.
     *
     * Ce seeder est idempotent : il peut être relancé sans créer de doublons.
     * Structure créée :
     *   - 1 enseignant avec 3 thématiques
     *   - 1 classe avec 4 étudiants inscrits
     *   - Groupe Alpha  : Alice (créateur) + Bob     | 2 thématiques
     *   - Groupe Bêta   : Claire (créateur) + David  | 1 thématique
     */
    public function run(): void
    {
        DB::transaction(function () {
            // ── 1. Enseignant ──────────────────────────────────────────────
            $enseignant = User::firstOrCreate(
                ['email' => 'demo.prof@muse.test'],
                [
                    'prenom' => 'Sophie',
                    'nom' => 'Tremblay',
                    'password' => Hash::make('Password12345!'),
                    'role' => 'enseignant',
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info("Enseignant : {$enseignant->prenom} {$enseignant->nom} (id={$enseignant->id})");

            // ── 2. Thématiques de l'enseignant ─────────────────────────────
            $t1 = Thematique::firstOrCreate(
                ['nom' => 'La Nouvelle-France', 'enseignant_id' => $enseignant->id],
                [
                    'description' => 'Colonisation française en Amérique du Nord.',
                    'periode_historique' => '1534 – 1763',
                    'enseignant_id' => $enseignant->id,
                ]
            );

            $t2 = Thematique::firstOrCreate(
                ['nom' => 'La Révolution tranquille', 'enseignant_id' => $enseignant->id],
                [
                    'description' => 'Modernisation profonde du Québec des années 60.',
                    'periode_historique' => '1960 – 1980',
                    'enseignant_id' => $enseignant->id,
                ]
            );

            $t3 = Thematique::firstOrCreate(
                ['nom' => 'Les Premières Nations du Québec', 'enseignant_id' => $enseignant->id],
                [
                    'description' => 'Histoire des peuples autochtones du Québec.',
                    'periode_historique' => 'Préhistoire – présent',
                    'enseignant_id' => $enseignant->id,
                ]
            );

            $this->command->info("Thématiques : {$t1->nom} | {$t2->nom} | {$t3->nom}");

            // ── 3. Classe ─────────────────────────────────────────────────
            $classe = Classe::firstOrCreate(
                ['code' => 'DEMO-330', 'enseignant_id' => $enseignant->id],
                [
                    'nom_cours' => 'Histoire du Québec (DÉMO)',
                    'description' => 'Classe de démonstration pour tester les groupes.',
                    'heures_par_semaine' => 3,
                    'groupe' => 'A',
                    'enseignant_id' => $enseignant->id,
                ]
            );

            $this->command->info("Classe : {$classe->nom_cours} (id={$classe->id})");

            // ── 4. Étudiants (4) ──────────────────────────────────────────
            $etudiants = collect([
                ['email' => 'alice@muse.test',  'prenom' => 'Alice',  'nom' => 'Gagnon'],
                ['email' => 'bob@muse.test',    'prenom' => 'Bob',    'nom' => 'Leblanc'],
                ['email' => 'claire@muse.test', 'prenom' => 'Claire', 'nom' => 'Roy'],
                ['email' => 'david@muse.test',  'prenom' => 'David',  'nom' => 'Côté'],
            ])->map(function (array $data) use ($classe) {
                $etudiant = User::firstOrCreate(
                    ['email' => $data['email']],
                    array_merge($data, [
                        'password' => Hash::make('Password12345!'),
                        'role' => 'etudiant',
                        'email_verified_at' => now(),
                    ])
                );

                // Inscrire dans la classe sans créer de doublon
                $classe->etudiants()->syncWithoutDetaching([$etudiant->id]);

                return $etudiant;
            });

            $this->command->info('Étudiants inscrits : '.$etudiants->pluck('prenom')->implode(', '));

            // ── 5. Nettoyer les groupes existants pour cette classe ────────
            // Supprime les groupes de démo précédents pour repartir proprement
            $classe->groupes()
                ->whereIn('nom', ['Équipe Alpha (DÉMO)', 'Équipe Bêta (DÉMO)'])
                ->each(fn (Groupe $g) => $g->delete());

            // ── 6. Groupe Alpha — Alice + Bob — 2 thématiques ────────────
            $alpha = Groupe::create([
                'nom' => 'Équipe Alpha (DÉMO)',
                'classe_id' => $classe->id,
                'created_by' => $etudiants[0]->id,
            ]);

            $alpha->membres()->attach([$etudiants[0]->id, $etudiants[1]->id]);
            $alpha->thematiques()->attach([$t1->id, $t2->id]);

            $this->command->info(
                "Groupe Alpha (id={$alpha->id}) : "
                ."{$etudiants[0]->prenom} + {$etudiants[1]->prenom} | "
                ."membres={$alpha->membres()->count()} | thématiques={$alpha->thematiques()->count()}"
            );

            // ── 7. Groupe Bêta — Claire + David — 1 thématique ───────────
            $beta = Groupe::create([
                'nom' => 'Équipe Bêta (DÉMO)',
                'classe_id' => $classe->id,
                'created_by' => $etudiants[2]->id,
            ]);

            $beta->membres()->attach([$etudiants[2]->id, $etudiants[3]->id]);
            $beta->thematiques()->attach([$t3->id]);

            $this->command->info(
                "Groupe Bêta (id={$beta->id}) : "
                ."{$etudiants[2]->prenom} + {$etudiants[3]->prenom} | "
                ."membres={$beta->membres()->count()} | thématiques={$beta->thematiques()->count()}"
            );

            // ── 8. Vérification finale ────────────────────────────────────
            $pivot_ge = DB::table('groupe_etudiant')
                ->whereIn('groupe_id', [$alpha->id, $beta->id])
                ->count();

            $pivot_gt = DB::table('groupe_thematique')
                ->whereIn('groupe_id', [$alpha->id, $beta->id])
                ->count();

            $this->command->info('─────────────────────────────────────────');
            $this->command->info("groupe_etudiant   : {$pivot_ge} lignes insérées (attendu : 4)");
            $this->command->info("groupe_thematique : {$pivot_gt} lignes insérées (attendu : 3)");
            $this->command->info('─────────────────────────────────────────');
            $this->command->info('Comptes de connexion (mot de passe : Password12345!) :');
            $this->command->info('  Enseignant : demo.prof@muse.test');
            $this->command->info('  Alice      : alice@muse.test  (créateur Groupe Alpha)');
            $this->command->info('  Bob        : bob@muse.test    (membre Groupe Alpha)');
            $this->command->info('  Claire     : claire@muse.test (créateur Groupe Bêta)');
            $this->command->info('  David      : david@muse.test  (membre Groupe Bêta)');
        });
    }
}
