# Plan — Musée Virtuel (Sprints 1–9)

> Généré le 2026-06-29. Sprint 0 complété (commit `6b4aeed`).

---

## Sprint 1 — Template visuel enseignant

### Tâche 1.1 — Initialisation du MuseeTemplate à la création du TypeProjet musée
- À la création d'un `TypeProjet` de type `musee`, créer automatiquement un enregistrement `MuseeTemplate` avec les valeurs par défaut.
- Service ou Observer sur `TypeProjet`.

### Tâche 1.2 — Interface de personnalisation du template
- Controller `MuseeTemplateController` : `show`, `update`
- Page Vue : sélecteur de palette prédéfinie (3–5 palettes harmonisées)
- Sélecteurs de polices pour chaque composant (liste limitée : 5–6 Google Fonts)
- Prévisualisation live dans la page (CSS variables injectées dynamiquement)
- Route : `PUT /types-projets/{typeProjet}/musee-template`

### Tâche 1.3 — Mode démo / preview du template
- Route `GET /types-projets/{typeProjet}/demo` → page Vue en lecture seule
- Rendre un faux projet musée avec du contenu placeholder (lorem ipsum)
- Appliquer les styles du `MuseeTemplate`
- Bouton "Tester le rendu" dans l'interface enseignant

### Tâche 1.4 — Gestion des catégories (Période & Région)
- Nouveaux `MuseePeriodeController` + `MuseeRegionController`
- CRUD Période dans les paramètres du cours (comme les thématiques existantes)
- CRUD Région idem
- Réordonner via drag-and-drop (même pattern que les sections existantes)
- Routes : `POST/PATCH/DELETE /cours/{cours}/musee-periodes`, idem pour régions

---

## Sprint 2 — Structure projet musée (côté étudiant)

### Tâche 2.1 — Création du projet musée
- `ProjetRecherche` de type musée se crée comme les autres (via le mécanisme existant)
- À la création, générer automatiquement un `musee_meta` avec un slug (basé sur nom du groupe + titre)
- Vérifier que le slug est unique en DB

### Tâche 2.2 — Formulaire de métadonnées & catégorisation
- Nouveau composant Vue `MuseeMetaForm`
- Champs : Titre du projet, texte d'intro (pour la carte galerie), image d'intro
- Sélecteurs : Période, Thème (thématiques existantes), Région
- Controller : `MuseeMetaController` → `update`
- Route : `PATCH /projets/{projet}/musee-meta`

### Tâche 2.3 — Section en-tête (entete)
- Composant d'édition de l'en-tête : image de fond (upload), titre, sous-titre
- Preview en temps réel avec les styles du template
- Stocker dans `musee_meta` (pas dans `musee_blocs`)
- L'image de fond est optionnelle (défini dans le template)

### Tâche 2.4 — Navigation dans le projet musée (layout éditeur)
- Nouvelle page Vue `Pages/Musee/Show.vue`
- Layout à deux panneaux : nav des sections à gauche, éditeur à droite
- Indicateurs visuels : section complète ✓ / incomplète / slot manquant
- Bouton "Aperçu public" → ouvre une fenêtre de preview
- Adapté au template de l'enseignant (polices, couleurs via CSS variables)

---

## Sprint 3 — Éditeur de blocs (texte & images de base)

### Tâche 3.1 — Infrastructure de l'éditeur en blocs
- Composant `BlocEditor.vue` : liste de blocs avec bouton "Ajouter un bloc" (menu : texte, image, séparateur)
- Drag-and-drop pour réordonner les blocs (même librairie que le reste du projet)
- Controller `MuseeBlocController` : `store`, `update`, `destroy`, `reorder`
- Routes : `POST/PATCH/DELETE /projets/{projet}/sections/{section}/blocs`

### Tâche 3.2 — Bloc Texte riche
- Éditeur WYSIWYG pour le bloc texte (Tiptap — compatible Vue 3)
- Styles contraints par le template enseignant (pas de choix de police/couleur libre)
- Support des hyperliens internes (entre sections) et externes
- Les liens externes sont stockés avec un flag `externe: true` dans le JSON du bloc
- Rendu des liens externes avec classe CSS identifiable (pour la correction)

### Tâche 3.3 — Bloc Image (upload de base)
- Upload image (jpeg, png, webp, gif)
- Stocker dans `musee_images`, servir depuis `public/musee/`
- Champ "Texte alternatif" + "Légende sous l'image"
- Controller `MuseeImageController` : `store`, `destroy`
- Route : `POST /projets/{projet}/musee-images`

### Tâche 3.4 — Vue publique de base du musée étudiant
- Nouvelle page Vue `Pages/Public/Musee/Show.vue` (route sans auth)
- Route publique : `GET /musee/{slug}`
- Appliquer le `MuseeTemplate` (CSS variables)
- Rendre l'en-tête, les sections, les blocs texte et images
- Incrémenter `musee_vues` à chaque visite (avec dédoublonnage par IP/24h)
- SEO de base : `<title>`, `<meta description>` depuis les métadonnées

### Tâche 3.5 — Tests Sprint 3
- Tests Pest : `MuseeBlocController` (store, update, destroy, reorder)
- Test : slug unique généré à la création
- Test : vue publique retourne 404 si projet non publié
- Test : comptage des vues (pas de doublon même IP)

---

## Sprint 4 — Médias images avancés

### Tâche 4.1 — Outil de crop d'image
- Intégrer une librairie de crop Vue (ex: vue-cropper ou cropperjs)
- Après upload, ouvrir le modal de crop
- Stocker les données de crop dans `musee_images.crop_data` (JSON : x, y, width, height, ratio)
- Appliquer le crop à l'affichage via CSS (`object-fit`, `object-position`) ou générer une image croppée côté serveur

### Tâche 4.2 — Bloc Carrousel d'images
- Nouveau type de bloc `carrousel` dans `musee_blocs`
- Interface pour ajouter/supprimer/réordonner des images dans le carrousel
- Composant Vue `BlocCarrousel.vue` pour l'éditeur ET pour la vue publique
- Navigation (flèches + points) dans le carrousel public

### Tâche 4.3 — Image latérale ancrée à un mot
- Dans le bloc texte, permettre d'associer une image à un mot spécifique (sélection de mot → assigner une image)
- Stocker l'ancre dans `musee_blocs.contenu` : `{ anchor_word: "Québec", image_id: 42, position: "droite" }`
- Rendu : l'image flotte à côté du texte au niveau du mot ancré

### Tâche 4.4 — Image en arrière-plan de l'en-tête (cas avancés)
- Overlay de couleur configurable sur l'image de fond (pour la lisibilité du titre)
- Choix de la position de l'image (cover, center, top)
- Prévisualisation en temps réel

---

## Sprint 5 — Vidéos & segments

### Tâche 5.1 — Bloc Vidéo (intégration player)
- Nouveau type de bloc `video` dans `musee_blocs`
- Utiliser les `GroupeVideo` existants du groupe OU permettre un lien URL externe (YouTube, Vimeo)
- Player HTML5 personnalisé : contrôles volume + vitesse (0.5x, 1x, 1.25x, 1.5x, 2x)
- Composant Vue `BlocVideo.vue`

### Tâche 5.2 — Liaison segments vidéo ↔ sections de texte
- Nouvelle table `musee_video_segments` : `bloc_id`, `section_id`, `debut_secondes`, `fin_secondes`, `label`
- Interface enseignant : définir les segments sur la timeline vidéo
- Rendu public : navigation section → player se positionne sur le segment lié
- Navigation bidirectionnelle : clic segment → aller à la section ; navigation section → player highlight le segment

### Tâche 5.3 — Différents formats vidéo
- Accepter : mp4, webm, mov (conversion FFmpeg via job existant si nécessaire)
- Option intégration par URL (YouTube embed, Vimeo embed)
- Stocker le type de source (`upload | youtube | vimeo`) dans `musee_blocs.contenu`

---

## Sprint 6 — Galerie publique

### Tâche 6.1 — Page galerie publique `/musee`
- Nouvelle page Vue `Pages/Public/Musee/Index.vue`
- Route publique : `GET /musee`
- Composant carte galerie : image d'intro + titre + auteur(s) + tags (période, thème, région)
- Affichage grid responsive
- Pagination ou infinite scroll

### Tâche 6.2 — Filtres de navigation
- Barre de filtres : Période, Thème, Région
- Filtrage côté serveur (query params : `/musee?periode=3&theme=5`)
- Filtres affichent seulement les valeurs qui ont des projets publiés
- URL partageables avec les filtres actifs

### Tâche 6.3 — Gestion de la visibilité & consentement
- Avant publication, confirmer le consentement des étudiants du groupe
- Option de pseudonymisation (afficher "Groupe 3" plutôt que les noms réels)
- L'enseignant contrôle quel cours est représenté dans la galerie publique

### Tâche 6.4 — Design de la galerie (Stitch)
- Implémenter le design issu de Stitch / Design AI (à recevoir)
- La galerie doit visuellement se distinguer de l'app Muse interne
- Design responsive mobile

---

## Sprint 7 — Correction & Publication

### Tâche 7.1 — Page de correction séparée (enseignant)
- Nouvelle page Vue `Pages/Musee/Correction.vue`
- Vue côte-à-côte : rendu du musée à gauche, panneau de correction à droite
- Critères de correction spécifiques aux projets musée (via `TypeProjetCritere` existant)
- Identifier visuellement les liens externes (couleur différente)
- Route : `GET /projets/{projet}/musee/correction`

### Tâche 7.2 — Duplication du projet pour modification prof
- Méthode `dupliquerPourPublication()` sur `ProjetRecherche`
- Copie toutes les tables liées au musée (meta, blocs, images, template appliqué)
- Le clone a `est_copie_prof = true` et `projet_original_id` → le projet étudiant
- L'original reste intact et consultable

### Tâche 7.3 — Publication (originale ou copie)
- Bouton "Publier l'original" → `est_publie = true` sur `musee_publications` du projet étudiant
- Bouton "Publier la copie modifiée" → même chose sur la copie prof
- Un seul des deux peut être publié (contrainte DB)
- Route : `POST /projets/{projet}/musee/publier`
- Notification à l'étudiant quand son projet est publié

### Tâche 7.4 — Workflow de correction complet
- États : `en_cours → remis → en_correction → corrigé → publié`
- Commentaires sur des blocs spécifiques (comme les annotations existantes)
- Score global + scores par section depuis les critères
- Export PDF de la grille de correction

---

## Sprint 8 — Hyperliens & navigation interne

### Tâche 8.1 — Liens inter-sections (ancres)
- Dans Tiptap, ajouter option "Lien vers une section de ce musée"
- Stocker comme `{ href: "#section-3", type: "interne" }`
- Rendre comme ancres HTML dans la vue publique
- IDs de section stables (basés sur l'ID, pas le titre)

### Tâche 8.2 — Liens vers d'autres musées
- Option "Lien vers un autre musée Muse" : sélecteur des projets publiés
- Stocker le slug cible
- Rendre comme lien interne (même domaine, pas de nouvelle fenêtre)

### Tâche 8.3 — Identification visuelle des liens externes (correction)
- En mode correction, liens avec `type: externe` surlignés en orange
- Panneau latéral liste tous les liens externes avec leur URL
- L'enseignant peut marquer chaque lien comme "vérifié ✓"

---

## Sprint 9 — Statistiques

### Tâche 9.1 — Dashboard stats enseignant
- Page stats : vues totales par projet de la classe, vues par jour (graphe simple)
- Top 3 projets les plus consultés
- Vues uniques vs vues totales (via `musee_vues`)
- Route : `GET /cours/{cours}/musee/stats`

### Tâche 9.2 — Intégration analytics (optionnel)
- Évaluer : analytics maison suffisant, ou intégrer Plausible (RGPD-friendly) ?
- Si Plausible : ajouter le snippet JS dans le layout de la vue publique uniquement
- Pas de Google Analytics (données hébergées en UE recommandé pour contexte éducatif québécois)

---

## Ordre recommandé

```
Sprint 0 ✅ → Sprint 1 → Sprint 2 → Sprint 3 → Sprint 4
                                        ↓
                                   Sprint 5 (vidéos)
                                   Sprint 6 (galerie)  ← en parallèle possible après Sprint 3
                                        ↓
                                   Sprint 7 (correction)
                                   Sprint 8 (liens)
                                   Sprint 9 (stats)
```

Sprint 4, 5, 6 peuvent démarrer en parallèle une fois Sprint 3 terminé.
