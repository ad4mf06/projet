---
name: navigateur
description: Trouve les fichiers pertinents pour une tâche sans lire tout le projet. Activer via /find <sujet> ou quand Claude s'apprête à explorer un grand nombre de fichiers. Réduit drastiquement la consommation de tokens en ciblant uniquement les fichiers nécessaires avant de les ouvrir.
---

# Navigateur de projet ciblé

Trouve les fichiers pertinents pour un sujet **sans scanner tout le projet**.

## Quand utiliser ce skill
- Avant d'explorer un module inconnu
- Quand la tâche touche plusieurs couches (Controller + Model + Vue + Test)
- Pour localiser où une fonctionnalité est implémentée

## Étapes

### 1. Recherche ciblée par grep
```bash
# Chercher par nom de classe, méthode ou concept
grep -r "$ARGUMENTS" app/ resources/js/ tests/ --include="*.php" --include="*.vue" --include="*.ts" -l

# Chercher dans les routes
grep -r "$ARGUMENTS" routes/ --include="*.php" -l
```

### 2. Affiner si trop de résultats (> 10 fichiers)
```bash
# Limiter aux fichiers les plus récemment modifiés
grep -r "$ARGUMENTS" app/ resources/js/ --include="*.php" --include="*.vue" -l | xargs ls -lt | head -10
```

### 3. Retourner la liste — sans lire le contenu

Présenter **maximum 10 fichiers** sous ce format :

```
📁 Fichiers pertinents pour : <sujet>

app/Services/InvoiceService.php        → Service principal de facturation
app/Models/Invoice.php                 → Modèle Eloquent + relations
app/Http/Controllers/InvoiceController.php → Controller CRUD
resources/js/pages/Invoices/Index.vue  → Page Vue liste
tests/Feature/InvoiceTest.php          → Tests existants
```

### 4. Demander confirmation
> "Veux-tu que j'ouvre ces fichiers pour commencer la tâche ?"

Ne pas ouvrir les fichiers sans confirmation explicite.

## Règles strictes
- **Ne jamais lire** `vendor/`, `node_modules/`, `storage/`, `public/build/`
- **Maximum 10 fichiers** listés — si plus, affiner la recherche
- **Ne pas lire le contenu** des fichiers — lister uniquement les chemins
- **Ne pas relire CLAUDE.md** s'il est déjà en contexte
