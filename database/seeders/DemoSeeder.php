<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Enseignant ───────────────────────────────────────────────────────
        /** @var User $prof */
        $prof = User::updateOrCreate(
            ['email' => 'prof@demo.com'],
            [
                'prenom' => 'Sophie',
                'nom' => 'Marchand',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
            ]
        );

        // ─── Thématique ───────────────────────────────────────────────────────
        /** @var Thematique $thematique */
        $thematique = Thematique::firstOrCreate(
            ['nom' => 'La Révolution tranquille', 'enseignant_id' => $prof->id],
            [
                'description' => 'Période de modernisation profonde du Québec : laïcisation, nationalisme, essor de l\'État québécois et transformations culturelles majeures.',
                'periode_historique' => '1960 – 1980',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Classe ───────────────────────────────────────────────────────────
        /** @var Classe $classe */
        $classe = Classe::firstOrCreate(
            ['code' => '330-DEM-01', 'enseignant_id' => $prof->id],
            [
                'nom_cours' => 'Histoire du Québec — Démo',
                'description' => 'Classe de démonstration pour présentation.',
                'code' => '330-DEM-01',
                'groupe' => '00001',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Étudiants ────────────────────────────────────────────────────────
        $etudiantsData = [
            ['prenom' => 'Léa',     'nom' => 'Tremblay', 'no_da' => 'DA100001', 'email' => 'etudiant1@demo.com'],
            ['prenom' => 'Maxime',  'nom' => 'Gagnon',   'no_da' => 'DA100002', 'email' => 'etudiant2@demo.com'],
            ['prenom' => 'Camille', 'nom' => 'Roy',       'no_da' => 'DA100003', 'email' => 'etudiant3@demo.com'],
            ['prenom' => 'Nathan',  'nom' => 'Bouchard',  'no_da' => 'DA100004', 'email' => 'etudiant4@demo.com'],
        ];

        /** @var User[] $etudiants */
        $etudiants = [];
        foreach ($etudiantsData as $data) {
            $etudiant = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'prenom' => $data['prenom'],
                    'nom' => $data['nom'],
                    'no_da' => $data['no_da'],
                    'password' => Hash::make('password'),
                    'role' => 'etudiant',
                    'email_verified_at' => now(),
                ]
            );

            // Inscrire dans la classe si pas encore inscrit dans une quelconque classe
            if (! $classe->etudiants()->whereKey($etudiant->id)->exists()
                && ! $etudiant->classesInscrites()->exists()
            ) {
                $classe->etudiants()->attach($etudiant->id, ['statut_cours' => 'Actif']);
            }

            $etudiants[] = $etudiant;
        }

        // ─── Groupe ───────────────────────────────────────────────────────────
        // Un seul groupe de démo par classe — on réutilise s'il existe déjà
        /** @var Groupe $groupe */
        $groupe = $classe->groupes()->firstOrCreate(
            ['created_by' => $etudiants[0]->id],
            [
                'classe_id' => $classe->id,
                'created_by' => $etudiants[0]->id,
            ]
        );

        // Attacher les membres (idempotent via syncWithoutDetaching)
        $groupe->membres()->syncWithoutDetaching(array_map(fn ($e) => $e->id, $etudiants));

        // Attacher la thématique
        $groupe->thematiques()->syncWithoutDetaching([$thematique->id]);

        // ─── Projet de recherche ──────────────────────────────────────────────
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id],
            [
                'groupe_id' => $groupe->id,
                'titre_projet' => 'La Révolution tranquille : rupture ou continuité dans l\'histoire du Québec ?',

                'introduction_amener' => 'Le Québec du début du XXe siècle est une société majoritairement rurale, profondément marquée par les valeurs catholiques et une économie dominée par une élite anglophone. Pendant des décennies, l\'Église catholique contrôle l\'éducation, la santé et les services sociaux, tandis que le gouvernement de Maurice Duplessis maintient un conservatisme politique qui freine toute modernisation de l\'État. Cette période, connue sous le nom de « Grande Noirceur », prend fin avec la mort de Duplessis en 1959 et l\'élection du Parti libéral de Jean Lesage en 1960.',

                'introduction_poser' => 'Dans quelle mesure la Révolution tranquille constitue-t-elle une véritable rupture avec le passé québécois, et quelles en sont les transformations les plus durables sur le plan social, économique et culturel ?',

                'introduction_diviser' => 'Pour répondre à cette question, nous examinerons d\'abord les réformes institutionnelles mises en place par l\'État québécois entre 1960 et 1970. Nous analyserons ensuite les transformations sociales et culturelles qui ont redéfini l\'identité québécoise. Enfin, nous évaluerons l\'héritage économique de cette période et son impact sur le nationalisme contemporain.',

                'dev_1_titre' => 'La réforme de l\'État et la laïcisation des institutions',
                'dev_1_contenu' => 'La principale transformation de la Révolution tranquille réside dans la récupération par l\'État des pouvoirs jusqu\'alors détenus par l\'Église. La création du ministère de l\'Éducation en 1964, suivant les recommandations de la Commission Parent, marque un tournant décisif : l\'éducation devient une responsabilité de l\'État, gratuite et accessible à tous. La création de la Caisse de dépôt et placement du Québec (1965), de la Régie des rentes (1965) et d\'Hydro-Québec nationalisée (1962) témoignent d\'une volonté de maîtriser les leviers économiques du développement. L\'expression « Maîtres chez nous » du gouvernement Lesage résume parfaitement cette ambition d\'autonomie collective.',

                'dev_2_titre' => 'Les transformations sociales et la montée du féminisme',
                'dev_2_contenu' => 'La Révolution tranquille s\'accompagne d\'une profonde transformation des mentalités et des structures sociales. Le taux de natalité, l\'un des plus élevés au monde dans les années 1950, chute rapidement dans les années 1960, phénomène connu sous le nom de « revanche des berceaux inversée ». Les femmes accèdent massivement au marché du travail et au monde universitaire. Le mouvement féministe québécois s\'affirme avec la création de la Fédération des femmes du Québec en 1966. Les mœurs évoluent, le mariage civil se banalise et le taux de pratique religieuse s\'effondre progressivement.',

                'dev_3_titre' => 'L\'affirmation de l\'identité nationale et le mouvement souverainiste',
                'dev_3_contenu' => 'La Révolution tranquille est indissociable de l\'émergence d\'un nouveau nationalisme québécois. Là où le nationalisme traditionnel était d\'inspiration catholique et conservateur, le néo-nationalisme des années 1960 est laïc, progressiste et axé sur l\'affirmation de la langue française et de la spécificité culturelle québécoise. La fondation du Rassemblement pour l\'indépendance nationale (RIN) en 1960 et du Parti Québécois en 1968 par René Lévesque incarnent cette nouvelle aspiration à la souveraineté. Le Front de libération du Québec (FLQ), même si marginal, illustre la radicalisation d\'une partie de ce mouvement lors de la Crise d\'Octobre 1970.',

                'dev_4_titre' => 'Le développement économique et la question linguistique',
                'dev_4_contenu' => 'Sur le plan économique, la Révolution tranquille amorce une modernisation accélérée du Québec. L\'État investit massivement dans les infrastructures, l\'éducation supérieure et les entreprises publiques. Les Québécois francophones, longtemps cantonnés à des emplois subalternes dans les entreprises anglophones, commencent à accéder à des postes de cadres et de direction. Cette prise de conscience conduit à la Commission Gendron (1968-1972), dont les travaux mèneront à la Loi 22 (1974) puis à la Charte de la langue française, la Loi 101, adoptée en 1977 sous le gouvernement Lévesque.',

                'dev_5_titre' => 'L\'héritage de la Révolution tranquille et ses limites',
                'dev_5_contenu' => 'Si la Révolution tranquille a profondément transformé le Québec, son bilan est nuancé. D\'un côté, elle a permis la création d\'un État moderne, la démocratisation de l\'éducation et l\'affirmation de l\'identité francophone. De l\'autre, la croissance rapide de l\'État a généré une dette publique significative et des bureaucraties parfois inefficaces. Certains historiens, comme Ronald Rudin, remettent en question le mythe de la « Grande Noirceur » et soulignent des éléments de continuité plutôt que de rupture. La Révolution tranquille reste néanmoins un moment fondateur dans la construction de l\'identité québécoise contemporaine.',
            ]
        );

        // ─── Conclusions individuelles ─────────────────────────────────────────
        $conclusions = [
            $etudiants[0]->id => 'La Révolution tranquille représente, à mes yeux, le moment le plus transformateur de l\'histoire du Québec moderne. En étudiant cette période, j\'ai réalisé à quel point la laïcisation des institutions a libéré la société québécoise d\'un carcan religieux qui freinait son développement. La nationalisation de l\'électricité et la création du ministère de l\'Éducation m\'apparaissent comme les réformes les plus symboliques : elles incarnent la volonté du peuple québécois de reprendre le contrôle de son destin. Ce qui me frappe le plus, c\'est la rapidité de ces transformations — en moins de vingt ans, le Québec a rattrapé un retard historique considérable.',

            $etudiants[1]->id => 'Mon analyse de la Révolution tranquille m\'amène à considérer surtout son impact sur l\'identité nationale. Le passage d\'un nationalisme religieux et conservateur à un nationalisme laïc et progressiste est fascinant. La création du Parti Québécois et l\'adoption de la Loi 101 sont des conséquences directes de cette période. Je pense que sans la Révolution tranquille, la question de la souveraineté du Québec n\'aurait pas pris la forme qu\'elle a connue lors des référendums de 1980 et 1995. Cette période a véritablement redéfini ce que signifie être Québécois au sein du Canada.',

            $etudiants[2]->id => 'En travaillant sur ce projet, c\'est surtout la dimension féministe de la Révolution tranquille qui a retenu mon attention. La transformation du rôle des femmes dans la société québécoise est remarquable : de la ménagère cantonnée au foyer, on passe à des femmes qui investissent l\'université, le marché du travail et la vie politique. La chute du taux de natalité est symptomatique d\'une prise en main par les femmes de leur vie reproductive. Je crois que cet aspect est souvent sous-estimé dans les récits historiques qui se concentrent davantage sur les grandes réformes étatiques et économiques.',

            $etudiants[3]->id => 'Ma réflexion sur la Révolution tranquille porte principalement sur la question des limites de cette transformation. Si les acquis sont indéniables, les travaux d\'historiens comme Ronald Rudin m\'ont conduit à questionner le mythe d\'un avant et après aussi tranché que le narratif officiel le suggère. La croissance de l\'État a certes modernisé le Québec, mais elle a aussi créé des dépendances et des inefficacités bureaucratiques que la société québécoise paie encore aujourd\'hui. La Révolution tranquille est un succès, mais un succès inachevé qui mérite une lecture critique plutôt qu\'une admiration inconditionnelle.',
        ];

        foreach ($conclusions as $userId => $contenu) {
            ProjetConclusion::updateOrCreate(
                ['projet_id' => $projet->id, 'user_id' => $userId],
                ['contenu' => $contenu]
            );
        }
    }
}
