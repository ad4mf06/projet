# Plan : Système de publication et d'édition de vidéos par groupe

## Objectif

Permettre aux étudiants de publier des vidéos dans leur groupe (distinct des médias existants de type photo/audio/document) et d'effectuer des manipulations simples directement dans le navigateur : rognage (trim), suppression de segments, avant de valider le rendu côté serveur via FFmpeg.

---

## Contexte technique

- **Ce qui existe déjà :** `GroupeMedia` (photo / audio / document, max 50 Mo), `ProjetSectionMedia` (vidéo dans sections de projet, max 200 Mo), `StoreUploadedFile` (action réutilisable), `HasPublicFile` (trait de suppression).
- **Ce qui manque :** un modèle `GroupeVideo` dédié aux vidéos de groupe, un pipeline d'édition serveur (FFmpeg), un composant Vue éditeur de timeline.
- **Approche editing :** le navigateur sélectionne les points de coupe via un lecteur HTML5 personnalisé ; la transformation réelle est déléguée à un Job Laravel (FFmpeg côté serveur). Pas de WebAssembly — trop lourd pour des machines d'étudiants.

---

## Analyse d'impact

| Zone | Fichiers | Impact |
|------|----------|--------|
| Migration | `database/migrations/` | Nouvelle table `groupe_videos` |
| Modèle | `app/Models/GroupeVideo.php` | Création |
| Policy | `app/Policies/GroupeVideoPolicy.php` | Création |
| Controller | `app/Http/Controllers/GroupeVideoController.php` | Création |
| Job | `app/Jobs/ProcessVideoEdit.php` | Création |
| Routes | `routes/web.php` | 6 nouvelles routes |
| Vue | `resources/js/Pages/…` / composants | 2–3 composants |
| Config | `config/ffmpeg.php` | Si `protonemedia/laravel-ffmpeg` installé |
| Tests | `tests/Feature/GroupeVideoTest.php` | Création |
| Composer | `composer.json` | 1 nouveau package |

---

## Risques identifiés

- **FFmpeg non installé sur le serveur** : le package `protonemedia/laravel-ffmpeg` nécessite que `ffmpeg` et `ffprobe` soient dans le PATH.
  → Mitigation : documenter l'installation, prévoir une vérification au démarrage de l'app (`php artisan ffmpeg:check`).
- **Taille des vidéos** : fichiers potentiellement > 500 Mo peuvent saturer `/tmp` ou `public/`.
  → Mitigation : limite `max:512000` (500 Mo) en validation, traitement en Job asynchrone.
- **Migration de l'enum** : si la DB est MySQL, tout ajout de valeur enum nécessite une migration.
  → Mitigation : `statut` stocké en `varchar`, pas en `enum`.
- **File d'attente non configurée** : les Jobs nécessitent `QUEUE_CONNECTION=database` ou `redis`.
  → Mitigation : un fallback `sync` est possible en dev pour le trim simple.
- **Droits d'accès aux vidéos** : les vidéos en `statut=brouillon` ne doivent pas être accessibles par URL directe.
  → Mitigation : Policy stricte + possibilité de servir via un controller (pas en `public/` nu).

---

## Tâches (dans l'ordre recommandé)

---

### Tâche 1 — Migration et modèle `GroupeVideo`

**Pourquoi en premier :** toute la suite en dépend (controller, job, vue).

**Fichiers à créer/modifier :**
- `database/migrations/YYYY_MM_DD_create_groupe_videos_table.php`
- `app/Models/GroupeVideo.php`

**Ce qu'il faut faire :**
- [ ] Générer la migration :
  ```bash
  php artisan make:migration create_groupe_videos_table
  ```
- [ ] Colonnes de la table :
  ```
  id, groupe_id (FK groupes), user_id (FK users),
  titre (varchar, nullable), description (text, nullable),
  nom_original (varchar), file_path (varchar),
  taille (bigint), duree (integer nullable — secondes),
  thumbnail_path (varchar nullable),
  statut (varchar default 'brouillon'),  -- brouillon | publié | archivé
  traitement_statut (varchar nullable),  -- en_attente | traitement | termine | erreur
  created_at, updated_at
  ```
- [ ] Créer `GroupeVideo` avec `HasPublicFile`, `$fillable`, relations `groupe()` et `auteur()`, accesseur `url`
- [ ] Ajouter relation `videos()` sur `Groupe` : `hasMany(GroupeVideo::class)`
- [ ] Lancer `php artisan migrate`

**Critère de succès :** `php artisan migrate` sans erreur, `Groupe::find(1)->videos` retourne une collection.

---

### Tâche 2 — Installer `protonemedia/laravel-ffmpeg` et configurer FFmpeg

**Pourquoi ici :** le Job de la tâche 4 en dépend ; mieux vaut détecter les problèmes tôt.

**Fichiers à modifier :**
- `composer.json` (via commande)
- `config/ffmpeg.php` (publié par le package)

**Ce qu'il faut faire :**
- [ ] Installer le package :
  ```bash
  composer require protonemedia/laravel-ffmpeg
  php artisan vendor:publish --provider="ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider"
  ```
- [ ] Vérifier que `ffmpeg` et `ffprobe` sont accessibles :
  ```bash
  ffmpeg -version
  ffprobe -version
  ```
- [ ] Configurer `config/ffmpeg.php` : chemin vers les binaires si nécessaire sous Windows/Herd
- [ ] Tester dans `tinker` :
  ```php
  FFMpeg::fromDisk('public')->open('medias/test.mp4')->getDurationInSeconds();
  ```

**Critère de succès :** `tinker` retourne la durée d'un fichier vidéo de test.

---

### Tâche 3 — Policy `GroupeVideoPolicy`

**Pourquoi avant le controller :** le controller s'appuie sur `authorize()`.

**Fichiers à créer/modifier :**
- `app/Policies/GroupeVideoPolicy.php`
- `app/Providers/AuthServiceProvider.php` ou `AppServiceProvider.php` (enregistrement)

**Ce qu'il faut faire :**
- [ ] Générer : `php artisan make:policy GroupeVideoPolicy --model=GroupeVideo`
- [ ] Implémenter :
  - `viewAny` : membre du groupe OU enseignant du cours
  - `view` : idem + si `statut = publié` sinon auteur/enseignant seulement
  - `create` : membre du groupe, cours non verrouillé
  - `update` (titre, description) : auteur OU enseignant
  - `delete` : auteur OU enseignant
  - `publish` : auteur OU enseignant
  - `edit` (lancer l'édition FFmpeg) : auteur OU enseignant
- [ ] Enregistrer la Policy dans `AppServiceProvider` (ou `AuthServiceProvider`)

**Critère de succès :** `php artisan tinker` + `app(Gate::class)->inspect($user, 'create', $video)` retourne le bon résultat.

---

### Tâche 4 — Job `ProcessVideoEdit`

**Pourquoi avant le controller :** le controller le dispatch.

**Fichiers à créer :**
- `app/Jobs/ProcessVideoEdit.php`

**Ce qu'il faut faire :**
- [ ] Générer : `php artisan make:job ProcessVideoEdit`
- [ ] Payload du Job :
  ```php
  public function __construct(
      public GroupeVideo $video,
      public float $debut,   // secondes
      public float $fin,     // secondes (0 = jusqu'à la fin)
      public array $coupes = [], // [{debut: float, fin: float}, ...] segments à supprimer
  ) {}
  ```
- [ ] Dans `handle()` :
  1. Marquer `traitement_statut = 'traitement'`
  2. Ouvrir le fichier avec `FFMpeg::fromDisk('public')->open($video->file_path)`
  3. Appliquer le trim principal (clip de `$debut` à `$fin`)
  4. Concaténer les segments autour des `$coupes` si nécessaire
  5. Exporter vers un nouveau fichier (chemin temporaire puis remplacement)
  6. Générer la miniature (frame à 1s) : `->exportFramesByAmount(1)`
  7. Extraire la durée finale
  8. Mettre à jour `GroupeVideo` : nouveau `file_path`, `thumbnail_path`, `duree`, `traitement_statut = 'termine'`
  9. En cas d'exception : `traitement_statut = 'erreur'`
- [ ] Configurer le timeout du Job (`$timeout = 600` pour 10 min)

**Critère de succès :** dispatch manuel du Job dans `tinker` produit un fichier vidéo tronqué.

---

### Tâche 5 — `GroupeVideoController`

**Fichiers à créer :**
- `app/Http/Controllers/GroupeVideoController.php`

**Routes à ajouter dans `routes/web.php` :**
```php
Route::prefix('cours/{cours}/classes/{classe}/groupes/{groupe}/videos')
     ->name('groupes.videos.')
     ->group(function () {
         Route::get('/',              [GroupeVideoController::class, 'index'])->name('index');
         Route::post('/',             [GroupeVideoController::class, 'store'])->name('store');
         Route::get('/{video}',       [GroupeVideoController::class, 'show'])->name('show');
         Route::patch('/{video}',     [GroupeVideoController::class, 'update'])->name('update');
         Route::delete('/{video}',    [GroupeVideoController::class, 'destroy'])->name('destroy');
         Route::patch('/{video}/publier', [GroupeVideoController::class, 'publier'])->name('publier');
         Route::post('/{video}/editer',   [GroupeVideoController::class, 'editer'])->name('editer');
     });
```

**Méthodes à implémenter :**
- [ ] `store` : validation (`mp4|webm|mov|avi|mkv`, max 500 Mo), `StoreUploadedFile`, création `GroupeVideo`
- [ ] `index` : retourner les vidéos du groupe (Inertia ou redirect selon l'UI)
- [ ] `show` : charger la vidéo avec `authorize('view')`
- [ ] `update` : modifier `titre` et `description` uniquement
- [ ] `destroy` : `$video->deleteWithFile()` + `thumbnail_path` si existant
- [ ] `publier` : toggle `statut` entre `brouillon` ↔ `publié`
- [ ] `editer` : valider `{debut, fin, coupes[]}`, dispatcher `ProcessVideoEdit`, retourner 202

**Critère de succès :** upload via `curl` ou formulaire crée un enregistrement en base.

---

### Tâche 6 — Interface Vue (upload + liste)

**Fichiers à créer/modifier :**
- Composant `VideoUploadForm.vue` (formulaire d'upload avec barre de progression)
- Composant `VideoCard.vue` (miniature, titre, statut, actions)
- Intégration dans la vue `Groupe/Show.vue` (section vidéos)

**Ce qu'il faut faire :**
- [ ] `VideoUploadForm.vue` : input file filtré sur vidéos, préview locale (`URL.createObjectURL`), barre de progression via Inertia `progress`, titre/description optionnels
- [ ] `VideoCard.vue` : miniature (ou icône si pas encore générée), titre, badge statut (`brouillon`/`publié`), boutons Publier/Éditer/Supprimer selon droits
- [ ] Ajouter section "Vidéos" dans `Groupe/Show.vue` avec liste de `VideoCard` et `VideoUploadForm`
- [ ] Gérer le `traitement_statut` : badge "En traitement" avec polling ou simple refresh

**Critère de succès :** upload visible dans la section groupe, statut affiché.

---

### Tâche 7 — Interface Vue (éditeur de timeline)

**Fichiers à créer :**
- `VideoEditor.vue` (page ou dialog)

**Ce qu'il faut faire :**
- [ ] Lecteur `<video>` natif HTML5 avec contrôles personnalisés (play/pause, time display)
- [ ] Timeline visuelle : barre avec deux poignées (début / fin) en JavaScript pur (pas de librairie supplémentaire)
  - Drag des poignées pour définir la plage à conserver
  - Affichage du timecode sous chaque poignée
- [ ] Section "Coupes" : bouton "Ajouter une coupure" → sélectionner [début, fin] du segment à supprimer
- [ ] Prévisualisation : un clic sur "Prévisualiser" jump au point de début sélectionné
- [ ] Bouton "Appliquer les modifications" → POST vers `groupes.videos.editer` avec `{debut, fin, coupes[]}`
- [ ] Afficher un état "En traitement…" pendant que le Job tourne

**Critère de succès :** l'éditeur s'ouvre, les poignées sont draggables, la soumission déclenche le Job.

---

### Tâche 8 — Tests Pest

**Fichiers à créer :**
- `tests/Feature/GroupeVideoTest.php`

**Ce qu'il faut faire :**
- [ ] `php artisan make:test --pest GroupeVideoTest`
- [ ] Tests d'upload :
  - Un membre peut uploader une vidéo
  - Un non-membre ne peut pas uploader
  - Un fichier trop grand est rejeté
  - Un type non vidéo est rejeté
- [ ] Tests de publication :
  - L'auteur peut publier sa vidéo
  - Un autre membre ne peut pas publier la vidéo d'autrui
  - L'enseignant peut publier n'importe quelle vidéo
- [ ] Tests de suppression :
  - L'auteur supprime → fichier physique supprimé
  - L'enseignant peut supprimer
  - Un non-membre ne peut pas supprimer
- [ ] Tests du Job `ProcessVideoEdit` (fake queue) :
  - Le Job est dispatchhé lors de `editer`
  - Le `traitement_statut` passe à `en_attente`
- [ ] Lancer : `php artisan test --compact --filter=GroupeVideoTest`

**Critère de succès :** tous les tests passent, couverture des cas d'autorisation.

---

### Tâche 9 — Validation finale et style

- [ ] `./vendor/bin/pint` — zéro erreur de style
- [ ] `php artisan test --compact` — suite complète au vert
- [ ] `php artisan wayfinder:generate --no-interaction` — routes typées à jour
- [ ] Tester manuellement : upload, publication, lancement d'édition, suppression
- [ ] Vérifier que les vidéos en `brouillon` ne sont pas accessibles à un membre externe au groupe

---

## Suggestions architecturales

- **Ne pas stocker les vidéos dans `public/images/`** — créer `public/medias/groupes/{groupe_id}/` pour les vidéos (cohérent avec `public/medias/projets/`).
- **Thumbnail lazy** : générer la miniature dans le même Job `ProcessVideoEdit`, pas à l'upload (économie CPU).
- **Polling côté Vue** : tant que `traitement_statut ≠ 'termine'`, un `setInterval` de 3s fait un `router.reload({only: ['video']})` — simple et sans WebSocket.
- **Archiver plutôt que supprimer** : envisager un `statut=archivé` avant la vraie suppression (possibilité de récupération).
- **`storeAs` vs `move`** : `StoreUploadedFile` utilise `move()` directement dans `public/`. Pour les grosses vidéos, considérer `Storage::disk('local')->putFile()` d'abord puis déplacement après traitement FFmpeg.
- **Windows/Herd** : sous Windows, les chemins FFmpeg doivent utiliser des slashes corrects ; tester `config/ffmpeg.php` avec les chemins absolus.

---

## Question de clarification (facultative)

Les "autres manipulations simples" mentionnées — pouvez-vous préciser lesquelles sont prioritaires ?
Options typiques : rotation/flip, ajustement du volume audio, sous-titres, concaténation de clips, ajout d'intro/outro.
Le plan ci-dessus couvre le **rognage (trim)** et la **suppression de segments internes**. Les autres manipulations s'ajoutent dans le Job existant.
