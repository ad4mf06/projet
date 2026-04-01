

# CLAUDE.md — Contexte du projet ## Stack technique | Clé | Valeur | |-----|--------| | Framework | Laravel 12 | | PHP | 8.4 | | Tests | Pest 4 | | Style | Pint (config : `pint.json`) | | Base de données | MySQL | | Frontend | Vue 3 + Inertia.js v2 + Tailwind CSS v4 | | Routes typées |

pasted

Peux tu simplifier ce claude.md et ajouter dans celui-ci l'instruction de toujour ce reférencer a /navigateur et /minimalisateur lorsqu'il est en utilisation ()

18 h 54
Peux-tu me préciser ce que sont /navigateur et /minimalisateur ? Ce sont des skills personnalisés, des commandes MCP, ou autre chose ? Et où se trouvent-ils (chemin dans le projet) ?

ils sont des agent ..md disponible dans ./claude/navigateur/skills.md et pareille pour minimalisateur

18 h 56
Voici le CLAUDE.md simplifié avec les références aux deux agents :


Simplified CLAUDE.md with agent references
Simplified CLAUDE.md with agent references
La réponse de Claude a été interrompue.
/.claude désoler

18 h 56
Pas de problème ! Donc les chemins sont :

.claude/navigateur/skills.md
.claude/minimalisateur/skills.md
C'est bien ça ? Je génère le fichier complet maintenant.


2 fichiers créés, a exécuté une commande, lu un fichier
2 fichiers créés, a exécuté une commande, lu un fichier
Les changements principaux par rapport à l'original :

Agents obligatoires ajoutés en haut du fichier, bien visibles, avec les bons chemins .claude/navigateur/skills.md et .claude/minimalisateur/skills.md
Tableau des packages supprimé — redondant avec le tableau de stack
Section "Foundation rules" fusionnée dans les conventions Laravel et le workflow
Section "Points d'entrée rapides" retirée — déléguée à l'agent /navigateur
Section tokens/navigation retirée — déléguée à l'agent /minimalisateur
Le workflow fait maintenant référence aux deux agents explicitement à l'étape 1
Claude
Document · MD




Claude est une IA et peut faire des erreurs. Veuillez vérifier les réponses.
Claude · MD
Copier

# CLAUDE.md — Contexte du projet

## ⚡ Agents obligatoires

> **Charger ces deux agents au début de chaque session, avant toute action.**

| Agent | Fichier |
|-------|---------|
| Navigateur | `.claude/navigateur/skills.md` |
| Minimalisateur | `.claude/minimalisateur/skills.md` |
 
---

## Stack technique

| Clé | Valeur |
|-----|--------|
| Framework | Laravel 12 |
| PHP | 8.4 |
| Tests | Pest 4 |
| Style | Pint (`pint.json`) |
| Base de données | MySQL |
| Frontend | Vue 3 + Inertia.js v2 + Tailwind CSS v4 |
| Routes typées | Wayfinder v0 |
| Auth | Laravel Fortify v1 |
| Serveur local | Laravel Herd (`.test`) |
 
---

## Conventions Laravel

### Nommage

| Type | Convention | Exemple |
|------|-----------|---------|
| Modèles | Singulier PascalCase | `User`, `InvoiceLine` |
| Controllers | Singulier + suffixe | `UserController` |
| Jobs | Impératif | `SendWelcomeEmail` |
| Events | Participe passé | `UserRegistered` |
| Listeners | Action sur l'event | `HandlePaymentFailed` |
| Requests | Action + ressource + Request | `StoreInvoiceRequest` |
| Policies | Ressource + Policy | `InvoicePolicy` |
| Migrations | snake_case descriptif | `create_invoice_lines_table` |

- Variables & méthodes : camelCase → `$invoiceLines`, `getActiveUsers()`
- Colonnes DB : snake_case → `created_at`, `user_id`
- Routes nommées : dot notation → `invoices.index`, `users.invoices.store`
- Booléens : préfixe `is`, `has`, `can` → `isEligibleForDiscount()`

### Structure

- Un Controller = une ressource. Single Action Controllers pour les actions hors CRUD.
- Logique métier dans des Services ou Actions, jamais dans les Controllers.
- Toujours déclarer `$fillable` ou `$guarded` sur les Modèles.
- Éviter le N+1 : utiliser `with()` pour les relations dans les listes.
- Utiliser la méthode `casts()` plutôt que `$casts`.
- Jamais `env()` hors des fichiers `config/` — toujours `config('...')`.
- Middleware dans `bootstrap/app.php` (pas de `Kernel.php` en Laravel 12).

---

## Documentation

Chaque méthode publique doit avoir un docblock :

```php
/**
 * Calcule le total HT d'une facture.
 *
 * @param  Invoice  $invoice
 * @return float
 *
 * @throws InvoiceNotFoundException
 */
public function calculateSubtotal(Invoice $invoice): float
```

Commenter le **pourquoi**, pas le quoi :
```php
✅ // L'API externe renvoie null au lieu de 0 — on normalise ici
❌ // On incrémente le compteur
```
 
---

## Skills par domaine

| Domaine | Skill |
|---------|-------|
| Routes dans les composants Vue | `wayfinder-development` |
| Tests | `pest-testing` |
| Composants Vue + Inertia | `inertia-vue-development` |
| Classes Tailwind | `tailwindcss-development` |
 
---

## Skills disponibles

| Commande | Description |
|----------|-------------|
| `/codex-review [chemin?]` | Review du code non commité |
| `/planificateur <description>` | Plan structuré d'un changement |
 
---

## Workflow attendu

1. Charger `/navigateur` et `/minimalisateur` en premier.
2. Vérifier si une classe/méthode similaire existe avant d'en créer une nouvelle.
3. Ajouter les docblocks sur tout le code généré.
4. Proposer les tests Pest en même temps que le code de production.
5. Ne jamais modifier un fichier sans expliquer brièvement pourquoi.
6. Utiliser `search-docs` (Boost MCP) avant de modifier du code lié à un package.

---

## Commandes utiles

```bash
# Tests
php artisan test --compact
php artisan test --compact --filter=NomDuTest
 
# Style
./vendor/bin/pint
./vendor/bin/pint --test
 
# Génération
php artisan wayfinder:generate --no-interaction
php artisan make:test --pest NomDuTest
 
# Debug
php artisan route:list
php artisan tinker --execute "..."
```
 
