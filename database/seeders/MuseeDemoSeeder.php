<?php

namespace Database\Seeders;

use App\Models\Cours;
use App\Models\EpoqueHistorique;
use App\Models\MuseeMeta;
use App\Models\MuseePublication;
use App\Models\MuseeTemplate;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Database\Seeder;

class MuseeDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer le prof et le cours de démo créés par DemoSeeder
        $prof = User::where('email', 'prof@demo.com')->first();

        if (! $prof) {
            $this->command->warn('MuseeDemoSeeder : prof@demo.com introuvable — lancez d\'abord DemoSeeder.');

            return;
        }

        $cours = Cours::where('code', '330-DEM-01')
            ->where('enseignant_id', $prof->id)
            ->first();

        if (! $cours) {
            $this->command->warn('MuseeDemoSeeder : cours 330-DEM-01 introuvable — lancez d\'abord DemoSeeder.');

            return;
        }

        // ─── TypeProjet musée ──────────────────────────────────────────────────
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'type' => 'musee'],
            [
                'enseignant_id' => $prof->id,
                'cours_id' => $cours->id,
                'nom' => 'Musée virtuel — La Révolution tranquille',
                'description' => 'Créez un musée virtuel interactif présentant votre enquête historique.',
                'type' => 'musee',
                'accessible' => true,
                'ponderation' => 20,
                'is_sommatif' => true,
            ]
        );

        // ─── Sections du musée ─────────────────────────────────────────────────
        $sections = [
            ['label' => 'Introduction',   'description' => 'Contexte historique et problématique de recherche.'],
            ['label' => 'Développement',  'description' => 'Analyse et présentation des sources et témoignages.'],
            ['label' => 'Conclusion',     'description' => 'Synthèse et réflexion critique.'],
        ];

        foreach ($sections as $index => $sec) {
            TypeProjetSection::firstOrCreate(
                ['type_projet_id' => $typeProjet->id, 'label' => $sec['label']],
                [
                    'type_projet_id' => $typeProjet->id,
                    'label' => $sec['label'],
                    'description' => $sec['description'],
                    'type' => 'texte',
                    'ordre' => $index + 1,
                ]
            );
        }

        // ─── Critère publication (auto-critère 20 pts) ─────────────────────────
        // Crée le critère uniquement s'il n'existe pas encore — évite les doublons
        // si le TypeProjet a été créé via le controller (qui le crée lui-même).
        $critereExistant = $typeProjet->criteres()
            ->where('is_musee_publication', true)
            ->exists();

        if (! $critereExistant) {
            $typeProjet->criteres()->create([
                'type' => 'positif',
                'contenu_type' => 'texte',
                'contenu' => '<p>Publication du musée dans la galerie.</p>',
                'pointage' => 20,
                'visible' => true,
                'is_musee_publication' => true,
                'ordre' => 1,
            ]);
        }

        // ─── Template visuel (couleurs par défaut) ─────────────────────────────
        MuseeTemplate::firstOrCreate(
            ['type_projet_id' => $typeProjet->id],
            [
                'type_projet_id' => $typeProjet->id,
                'font_titre_page' => 'Georgia, serif',
                'font_sous_titre' => 'Georgia, serif',
                'font_titre_section' => 'Georgia, serif',
                'font_corps' => 'system-ui, sans-serif',
                'font_legende' => 'system-ui, sans-serif',
                'couleur_fond' => '#ffffff',
                'couleur_titre' => '#1a1a2e',
                'couleur_corps' => '#374151',
                'couleur_accent' => '#6d28d9',
                'couleur_lien_externe' => '#7c3aed',
                'palette' => 'violet',
                'theme' => 'classique',
            ]
        );

        // ─── Projets pour les groupes existants ────────────────────────────────
        // Utiliser l'époque "La Révolution tranquille" et une thématique globale CEGEP
        // (etablissement_id null) pour que la valeur pré-assignée soit dans la liste du select.
        $epoque = EpoqueHistorique::where('nom', 'La Révolution tranquille')->first()
            ?? EpoqueHistorique::orderBy('ordre')->first();
        $thematique = Thematique::whereNull('etablissement_id')
            ->where('nom', 'Culture, arts et identité')
            ->first()
            ?? Thematique::whereNull('etablissement_id')->orderBy('nom')->first();

        foreach ($cours->classes as $classe) {
            foreach ($classe->groupes as $groupe) {
                /** @var ProjetRecherche $projet */
                $projet = ProjetRecherche::firstOrCreate(
                    ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
                    [
                        'groupe_id' => $groupe->id,
                        'type_projet_id' => $typeProjet->id,
                    ]
                );

                // MuseePublication (brouillon par défaut)
                MuseePublication::firstOrCreate(
                    ['projet_recherche_id' => $projet->id],
                    [
                        'projet_recherche_id' => $projet->id,
                        'est_copie_prof' => false,
                        'est_publie' => false,
                        'statut' => MuseePublication::STATUT_BROUILLON,
                    ]
                );

                // MuseeMeta avec titre et slug uniques
                $titre = 'La Révolution tranquille — '.($groupe->nom ?? "Groupe {$groupe->id}");

                MuseeMeta::firstOrCreate(
                    ['projet_recherche_id' => $projet->id],
                    [
                        'projet_recherche_id' => $projet->id,
                        'epoque_historique_id' => $epoque?->id,
                        'thematique_id' => $thematique?->id,
                        'slug' => MuseeMeta::genererSlug($titre),
                        'entete_titre' => $titre,
                        'entete_sous_titre' => 'Musée virtuel — 330-DEM-01',
                        'entete_overlay_couleur' => '#00000050',
                        'entete_image_position' => 'center',
                        'intro_texte' => 'Ce musée virtuel présente notre enquête historique sur la Révolution tranquille au Québec, une période de transformations profondes qui a redéfini l\'identité québécoise.',
                    ]
                );
            }
        }

        $this->command->info('MuseeDemoSeeder : TypeProjet musée créé pour le cours 330-DEM-01.');
    }
}
