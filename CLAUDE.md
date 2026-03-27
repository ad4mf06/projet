# CLAUDE.md — Contexte du projet

## Stack technique

| Clé | Valeur |
|-----|--------|
| Framework | Laravel 12 |
| PHP | 8.4 |
| Tests | Pest 4 |
| Style | Pint (config : `pint.json`) |
| Base de données | MySQL |
| Frontend | Vue 3 + Inertia.js v2 + Tailwind CSS v4 |
| Routes typées | Wayfinder v0 |
| Auth | Laravel Fortify v1 |
| Serveur local | Laravel Herd (`.test`) |

---

## ⚡ Règles de lecture — Économie de tokens

> Ces règles s'appliquent **à chaque nouvelle session**. Les respecter réduit l'usage de tokens de ~60%.

### Ne jamais lire au démarrage
- `vendor/` `node_modules/` `storage/` `public/build/` `public/hot`
- `*.lock` `*.log` `_ide_helper*.php` `.env`
- `bootstrap/cache/`

### Stratégie de navigation
1. **Lire ce fichier en premier** — il contient tout le contexte global nécessaire.
2. **Cibler avant de lire** — utiliser `grep` pour localiser les fichiers pertinents avant de les ouvrir.
3. **Maximum 5 fichiers** pour démarrer une tâche — demander confirmation avant d'en ouvrir d'autres.
4. **Jamais lire un dossier entier** — toujours cibler un fichier précis.

### Points d'entrée rapides
| Besoin | Fichier à lire |
|--------|---------------|
| Routes web | `routes/web.php` |
| Routes API | `routes/api.php` |
| Middleware / providers | `bootstrap/app.php` |
| Modèles | `app/Models/` |
| Services métier | `app/Services/` |
| Pages Vue | `resources/js/pages/` |
| Composants Vue | `resources/js/components/` |
| Config Vite | `vite.config.js` |

---

## Skills disponibles

| Commande | Description |
|----------|-------------|
| `/codex-review [chemin?]` | Review du code non commité : détection de duplication, refactoring suggéré, Pint en dry-run |
| `/planificateur <description>` | Plan structuré d'un changement avec analyse d'impact, tâches ordonnées et estimation |

---

## Conventions Laravel — À respecter absolument

### Nommage des classes et fichiers

| Type | Convention | Exemple |
|------|-----------|---------|
| Modèles | Singulier PascalCase | `User`, `InvoiceLine` |
| Controllers | Singulier + suffixe | `UserController`, `InvoiceController` |
| Jobs | Impératif | `SendWelcomeEmail`, `ProcessPayment` |
| Events | Participe passé | `UserRegistered`, `PaymentFailed` |
| Listeners | Action sur l'event | `HandlePaymentFailed`, `NotifyUserOnRegistration` |
| Requests | Action + ressource + Request | `StoreInvoiceRequest`, `UpdateUserRequest` |
| Policies | Ressource + Policy | `InvoicePolicy`, `UserPolicy` |
| Migrations | snake_case descriptif | `create_invoice_lines_table`, `add_status_to_orders_table` |

### Nommage des variables, méthodes et routes

- Variables & méthodes : camelCase → `$invoiceLines`, `getActiveUsers()`
- Colonnes DB : snake_case → `created_at`, `user_id`, `is_active`
- Routes nommées : dot notation → `invoices.index`, `invoices.show`, `users.invoices.store`
- Méthodes booléennes : préfixe `is`, `has`, `can`, `should` → `isEligibleForDiscount()`, `hasActiveSubscription()`

### Structure

- Un Controller = une ressource. Single Action Controllers pour les actions hors CRUD.
- La logique métier dans des Services ou Actions, pas dans les Controllers.
- Toujours déclarer `$fillable` ou `$guarded` sur les Modèles.
- Éviter le N+1 : utiliser `with()` pour les relations dans les listes.

---

## Documentation — Standards obligatoires

### Docblocks PHPDoc

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

- Première ligne : phrase courte à l'indicatif présent ("Calcule...", "Retourne...", "Envoie...")
- Documenter les `@throws` si la méthode peut lever une exception
- Méthodes privées simples (< 5 lignes, intention évidente) : docblock optionnel

### Commentaires inline

Commenter le **pourquoi**, pas le quoi :

```php
✅ // L'API externe renvoie null au lieu de 0 — on normalise ici
❌ // On incrémente le compteur
```

---

## Workflow attendu de Claude

1. **Lire ce fichier** avant toute exploration du projet.
2. Vérifier si une classe/méthode similaire existe déjà avant d'en créer une nouvelle.
3. Toujours ajouter les docblocks sur le code généré.
4. Proposer les tests Pest en même temps que le code de production.
5. Signaler si une décision sort des conventions Laravel standard.
6. Ne jamais modifier un fichier sans expliquer brièvement pourquoi.
7. Utiliser `search-docs` (Boost MCP) avant de modifier du code lié à un package.

---

## Commandes utiles

```bash
# Tests
php artisan test --compact
php artisan test --compact --filter=NomDuTest

# Style
./vendor/bin/pint
./vendor/bin/pint --test        # dry-run

# Génération
php artisan wayfinder:generate --no-interaction
php artisan make:test --pest NomDuTest

# Debug
php artisan route:list
php artisan config:show app
php artisan tinker --execute "..."
```

---

## Foundation rules (Laravel Boost)

> Les règles ci-dessous proviennent des guidelines Laravel Boost et s'appliquent à ce projet.

### Packages & versions actifs

| Package | Version |
|---------|---------|
| `laravel/framework` | v12 |
| `inertiajs/inertia-laravel` | v2 |
| `laravel/fortify` | v1 |
| `laravel/wayfinder` | v0 |
| `laravel/boost` | v2 |
| `laravel/pint` | v1 |
| `pestphp/pest` | v4 |
| `@inertiajs/vue3` | v2 |
| `tailwindcss` | v4 |
| `vue` | v3 |

### Skills à activer par domaine

| Domaine | Skill |
|---------|-------|
| Routes dans les composants Vue | `wayfinder-development` |
| Écriture / modification de tests | `pest-testing` |
| Composants Vue + Inertia | `inertia-vue-development` |
| Classes Tailwind dans les templates | `tailwindcss-development` |

### Règles structurelles importantes

- **Laravel 12** : middleware dans `bootstrap/app.php`, pas de `Kernel.php`.
- **Modèles** : utiliser la méthode `casts()` plutôt que `$casts` (suivre les conventions existantes).
- **Env** : jamais `env()` hors des fichiers `config/` — toujours `config('...')`.
- **Pint** : lancer `vendor/bin/pint --dirty --format agent` après toute modification PHP.
- **Vite** : si une modification frontend n'est pas visible, relancer `npm run dev` ou `composer run dev`.
- **Tests** : chaque changement doit être couvert par un test. Ne jamais supprimer un test sans approbation.
- **Herd** : le site est toujours disponible via `https://[nom-projet].test` — ne pas lancer de commande pour le démarrer.
