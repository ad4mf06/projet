# CLAUDE.md — Contexte du projet

## Stack technique
- **Framework** : Laravel 11
- **PHP** : 8.3
- **Tests** : Pest
- **Style** : Pint (config : `pint.json`)
- **Base de données** : MySQL <!-- Ajuste si nécessaire -->

---

## Skills disponibles

| Commande | Description |
|----------|-------------|
| `/codex-review [chemin?]` | Review du code non commité : détection de duplication, refactoring suggéré, Pint en dry-run |
| `/planificateur <description>` | Plan structuré d'un changement avec analyse d'impact, tâches ordonnées et estimation |

---

## Conventions Laravel — À respecter absolument

### Nommage des classes et fichiers
- **Modèles** : singulier PascalCase → `User`, `InvoiceLine`
- **Controllers** : singulier + suffixe → `UserController`, `InvoiceController`
- **Jobs** : impératif → `SendWelcomeEmail`, `ProcessPayment`
- **Events** : participe passé → `UserRegistered`, `PaymentFailed`
- **Listeners** : action sur l'event → `HandlePaymentFailed`, `NotifyUserOnRegistration`
- **Requests** : action + ressource + Request → `StoreInvoiceRequest`, `UpdateUserRequest`
- **Policies** : ressource + Policy → `InvoicePolicy`, `UserPolicy`
- **Migrations** : snake_case descriptif → `create_invoice_lines_table`, `add_status_to_orders_table`

### Nommage des variables, méthodes et routes
- **Variables & méthodes** : camelCase → `$invoiceLines`, `getActiveUsers()`
- **Colonnes DB** : snake_case → `created_at`, `user_id`, `is_active`
- **Routes nommées** : dot notation → `invoices.index`, `invoices.show`, `users.invoices.store`
- **Méthodes booléennes** : préfixe `is`, `has`, `can`, `should` → `isEligibleForDiscount()`, `hasActiveSubscription()`

### Structure
- Un Controller = une ressource. **Single Action Controllers** pour les actions hors CRUD.
- La logique métier dans des **Services** ou **Actions**, pas dans les Controllers.
- Toujours déclarer `$fillable` ou `$guarded` sur les Modèles.
- Éviter le **N+1** : utiliser `with()` pour les relations dans les listes.

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
- Commenter le **pourquoi**, pas le **quoi**
- ✅ `// L'API externe renvoie null au lieu de 0 — on normalise ici`
- ❌ `// On incrémente le compteur`

---

## Workflow attendu de Claude

1. Vérifier si une classe/méthode similaire existe déjà avant d'en créer une nouvelle.
2. Toujours ajouter les docblocks sur le code généré.
3. Proposer les tests **Pest** en même temps que le code de production.
4. Signaler si une décision sort des conventions Laravel standard.
5. Ne jamais modifier un fichier sans expliquer brièvement pourquoi.

---

## Commandes utiles

```bash
# Tests
./vendor/bin/pest

# Style
./vendor/bin/pint
./vendor/bin/pint --test   # dry-run
```
