---
name: minimalisateur
description: Charge le contexte minimal nécessaire pour démarrer une tâche précise. Activer en début de session ou quand Claude s'apprête à lire beaucoup de fichiers. Demande confirmation avant d'ouvrir plus de 5 fichiers. Réduit la consommation de tokens de 50-70% par session en évitant le chargement inutile de fichiers non pertinents.
---

# Minimalisateur de contexte

Charge **uniquement** ce qui est nécessaire pour une tâche précise.

## Quand utiliser ce skill
- En début de chaque nouvelle session de travail
- Avant d'attaquer une tâche dans un module inconnu
- Quand le contexte actuel commence à être lourd (longue conversation)

## Étapes

### 1. Lire CLAUDE.md (toujours, en premier)
CLAUDE.md contient tout le contexte global — stack, conventions, points d'entrée.
Ne pas lire d'autres fichiers de config tant que CLAUDE.md n'est pas lu.

### 2. Identifier les 3-5 fichiers clés pour la tâche
En se basant sur CLAUDE.md et la description de la tâche, identifier :

| Couche | Fichier type |
|--------|-------------|
| Route | `routes/web.php` (section concernée uniquement) |
| Controller | `app/Http/Controllers/XxxController.php` |
| Model | `app/Models/Xxx.php` |
| Service | `app/Services/XxxService.php` |
| Vue | `resources/js/pages/Xxx/Action.vue` |
| Test | `tests/Feature/XxxTest.php` |

### 3. Résumer le contexte chargé
Après lecture, produire un résumé de **10 lignes max** :

```
📋 Contexte chargé pour : <tâche>

Fichiers lus (4) :
  - app/Models/Invoice.php         → Modèle, relations: lines, payments
  - app/Services/InvoiceService.php → calculateSubtotal(), markAsPaid()
  - app/Http/Controllers/InvoiceController.php → CRUD standard
  - tests/Feature/InvoiceTest.php  → 12 tests existants

Prêt à : <description courte de ce qui peut être fait maintenant>
```

### 4. Demander avant d'aller plus loin
> "Veux-tu que j'ouvre des fichiers supplémentaires, ou on commence avec ce contexte ?"

## Règles strictes
- **Maximum 5 fichiers** ouverts sans confirmation
- **Jamais lire** `vendor/`, `node_modules/`, `storage/`, `public/build/`, `*.lock`
- **CLAUDE.md toujours en premier** — il évite de chercher dans d'autres fichiers de config
- **Une tâche à la fois** — ne pas charger le contexte de plusieurs fonctionnalités en parallèle
- Si la tâche change en cours de session → proposer de **réinitialiser le contexte**
