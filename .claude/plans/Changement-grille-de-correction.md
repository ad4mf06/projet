# Plan : Critères de correction
**Branche :** `Changement-grille-de-correction`
**Date :** 2026-06-12

> Sprint 1 (migrations + modèles) est considéré comme complété ou sera fait séparément.
> Les sprints ci-dessous sont à implémenter dans l'ordre.

---

## Sprint 2 — Backend : Définition des critères (TypeProjet)
*Objectif : un enseignant peut créer/modifier/supprimer des critères via API. Pas encore d'UI.*

- [ ] **T6** `TypeProjetCritereController` — store, update, destroy, reorder
  - Suit le même pattern que `TypeProjetTacheController` et `QuestionBanqueController`
  - `store` : crée un critère (global si section_id absent, sinon par section), calcule le prochain ordre
  - `update` : met à jour le critère
  - `destroy` : supprime et renumérote les restants
  - `reorder` : accepte tableau d'IDs, met à jour `ordre` séquentiellement
  - Validation : pointage > 0, type in ['positif','negatif'], contenu_type in ['texte','echelle'], section appartient au typeProjet si fournie, schéma de l'échelle valide
- [ ] **T7** Routes + régénération Wayfinder
  ```
  POST   /cours/{cours}/types-projets/{typeProjet}/criteres            → types-projets.criteres.store
  PATCH  /cours/{cours}/types-projets/{typeProjet}/criteres/reorder    → types-projets.criteres.reorder
  PUT    /cours/{cours}/types-projets/{typeProjet}/criteres/{critere}  → types-projets.criteres.update
  DELETE /cours/{cours}/types-projets/{typeProjet}/criteres/{critere}  → types-projets.criteres.destroy
  ```
  Puis : `php artisan wayfinder:generate --no-interaction`
- [ ] **T8** `TypeProjetController::edit()` — eager-loader `sections.criteres` (triés par ordre) + `criteresGlobaux` (section_id = null) + passer `pointage` dans chaque section
- [ ] **T9** Requests de validation : `StoreCritereRequest`, `UpdateCritereRequest`

---

## Sprint 3 — Backend : Application de la correction
*Objectif : un enseignant peut appliquer/retirer des corrections via API. Calcul de score fonctionnel et testé.*

- [ ] **T10** `ProjetRechercheController` — nouvelles méthodes :
  - `upsertCritereCorrection(Request, ..., TypeProjetCritere)` : crée ou met à jour la correction (points, commentaire, verifie), user_id null = tous ou ID étudiant du groupe
  - `destroyCritereCorrection(..., ProjetCritereCorrection)` : supprime (reset à non-appliqué)
  - `clonerCritereCorrection(Request, ..., ProjetCritereCorrection)` : duplique une correction "tous" en correction spécifique à un étudiant (source_id = correction d'origine)
  - `toggleCocheCritere(Request, ..., TypeProjetCritere)` : toggle coché étudiant (suivi personnel)
- [ ] **T11** Routes correction dans `routes/web.php` :
  ```
  PUT    .../projets/{projet}/criteres/{critere}/correction           → upsertCritereCorrection
  DELETE .../projets/{projet}/criteres/corrections/{correction}       → destroyCritereCorrection
  POST   .../projets/{projet}/criteres/corrections/{correction}/cloner → clonerCritereCorrection
  PATCH  .../projets/{projet}/criteres/{critere}/cocher               → toggleCocheCritere
  ```
  Puis régénérer Wayfinder.
- [ ] **T12** `ProjetRechercheController::show()` — enrichir :
  - Charger `typeProjet.sections.criteres` (ordered) + `typeProjet.criteresGlobaux`
  - Charger `projet.critereCorrections` groupées par `(critere_id, user_id)`
  - Charger les coches de l'étudiant courant
  - Passer `criteresGlobaux`, `correctionsCriteres`, `cochesCriteres` à la vue
- [ ] **T13** Calcul de score `scoreSection()` sur `ProjetRecherche` ou `CalculerScoreProjetAction` :
  ```
  Base = section.pointage
  Pour chaque critère POSITIF :
    correction = chercher user_id=userId, fallback user_id=null
    si correction → ajouter min(correction.points, critere.pointage)
    sinon → 0 (non attribué)
  Pour chaque critère NÉGATIF :
    correction = chercher user_id=userId, fallback user_id=null
    si correction → soustraire correction.points
  Retourner max(0, résultat)
  ```
- [ ] **T20** Tests Pest : `TypeProjetCritereTest.php` + `ProjetCritereCorrectionsTest.php`
  - Cas : créer positif/négatif/global/avec-échelle, droits enseignant vs étudiant
  - Cas : crochet vert = tous les points, partiel 3/5, malus individuel, clone surpasse "tous", visibilité étudiant

---

## Sprint 4 — Frontend : Édition TypeProjet
*Objectif : l'enseignant peut configurer ses critères depuis la page TypeProjet/Edit.*

- [ ] **T13** `resources/js/components/EchelleBuilder.vue`
  - Props : `modelValue: EchelleNiveau[]`, `pointageTotal: number`
  - Tableau lignes éditables : label (requis), points (requis), description (optionnel)
  - Bouton "+ Ajouter un niveau" (min 2 niveaux)
  - Bouton "Diviser automatiquement" → distribue `pointageTotal` équitablement
  - Total en bas → rouge si Σpoints ≠ pointageTotal
  - Drag-to-reorder des niveaux
- [ ] **T14** `resources/js/components/CritereForm.vue`
  - Props : `typeProjetId`, `sectionId: number | null`, `critere?: TypeProjetCritere`
  - Champs : toggle positif/négatif, champ pointage décimal, checkbox visible
  - Toggle texte/échelle → si texte : textarea/RichEditor ; si échelle : `<EchelleBuilder>` (positif seulement)
  - Submit via `useForm` Inertia (POST create / PUT update)
- [ ] **T15** `resources/js/pages/TypeProjet/Edit.vue`
  - Champ `pointage` numérique par section (inline dans chaque card section)
  - Card "Critères globaux" avant les sections (section_id = null) avec liste + `CritereForm`
  - Panneau "Critères" collapsible dans chaque section card (après label/description) avec liste + `CritereForm`
  - Boutons "Tout rendre visible / invisible" par type (positif/négatif) pour mass-toggle `visible`
- [ ] **T19** i18n `fr.json` + `en.json` — clés dans namespace `criteres` :
  - positif, negatif, pointage, visible, echelle, ajouter_critere, verifier, appliquer_malus, commentaire, dupliquer, tout_cocher_positifs, tout_appliquer_negatifs, indicateur_etudiant, niveaux_echelle, diviser_automatiquement, total_invalide, etc.

---

## Sprint 5 — Frontend : Correction & Vue étudiant
*Objectif : correction complète dans Show.vue, vue étudiant avec règles de visibilité. Seeders de démo.*

- [ ] **T16** `resources/js/components/CritereCorrection.vue` (vue enseignant)
  - Props : `critere`, `correction?`, `membres`, `estEnseignant`
  - **Positif** : crochet vert (tous pts) OU champ manuel (0→pointage), si echelle : radio par niveau, cible "Tous" | dropdown étudiant, bouton commentaire (toggle textarea indenté), bouton dupliquer
  - **Négatif** : champ points (préfill critere.pointage), cible "Tous" | étudiant, bouton appliquer/retirer, bouton commentaire, bouton dupliquer
  - Émet `@saved`, `@removed`, `@cloned`
- [ ] **T17** `resources/js/components/CritereEtudiant.vue` (vue étudiant)
  - **Mode rédaction** (`correction_visible=false`, `critere.visible=true`) : texte + pointage + checkbox personnelle (non correctif, `toggleCocheCritere`)
  - **Mode correction visible** (`correction_visible=true`) : positif → toujours visible avec pts obtenus ; négatif → visible SEULEMENT si correction existe pour cet étudiant ; commentaire prof si présent
- [ ] **T18** `resources/js/pages/Projets/Show.vue`
  - Bloc critères globaux avant la première section : `CritereCorrection` (enseignant) ou `CritereEtudiant` (étudiant)
  - Dans chaque section card, après `CommentaireEnseignant` : liste des critères via `CritereCorrection`/`CritereEtudiant`
  - Boutons bulk (enseignant) : "✓ Tout cocher positifs" + "Appliquer tous les négatifs" (avec confirmation)
  - Types TypeScript : `TypeProjetCritere`, `ProjetCritereCorrection`, `ProjetCritereEtudiantCoche`
- [ ] **T21** Seeders + nettoyage
  - Ajouter critères de démo dans `DemoSeeder` (1 critère positif, 1 négatif, 1 avec échelle)
  - `./vendor/bin/pint` — normaliser le style
  - `php artisan test --compact` — suite verte

---

## Rappel architecture clé

| Concept | Implémentation |
|---------|---------------|
| Critère positif | Points retirés par défaut, ajoutés à la vérification |
| Critère négatif | Points déduits quand correction appliquée |
| "Tous" vs individuel | `user_id = null` (tous) ou `user_id = X` (clone) dans `projet_critere_corrections` |
| user_id NULL dans UNIQUE | Utiliser valeur sentinelle `0` ou `COALESCE(user_id, 0)` dans l'index |
| Coches étudiant | Table `projet_critere_coches`, aucun impact sur la correction |
| Score calculé | À la volée, jamais stocké en DB |
