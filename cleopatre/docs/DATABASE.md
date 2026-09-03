# CLÉOPÂTRE — Schéma SQLite

Fichier : `database/cleopatre.sqlite` (WAL, `foreign_keys=ON`, `busy_timeout=5000`). Jamais exposé publiquement (`.htaccess` + `Require all denied`).

## Tables

### users
- `id` PK, `uuid` UNIQUE, `first_name`, `last_name`, `email` UNIQUE COLLATE NOCASE, `phone`, `password_hash`, `role` (customer|admin), `status` (active|disabled), `email_verified`, `created_at`, `updated_at`, `last_login_at`
- Index : `email`, `role`, `status`

### user_addresses
- `id` PK, `user_id` FK CASCADE, `label`, `first_name`, `last_name`, `phone`, `address`, `city`, `postal_code`, `additional_information`, `is_default`, `created_at`, `updated_at`
- Index : `user_id`

### sessions
- `id` PK, `user_id` FK, `payload`, `last_activity`, `created_at` (réservé, sessions PHP natives utilisées)

### categories
- `slug` PK, `name`, `eyebrow`, `tagline`, `description`, `intro`, `accent`, `surface`, `form`, `keywords` (JSON)

### subcategories
- `id` PK, `category_slug` FK, `slug`, `name`, UNIQUE(category_slug, slug)

### brands
- `slug` PK, `name`, `country`, `est`, `letter`, `featured`, `tint`, `tagline`, `story` (JSON), `signature`, `values_json` (JSON)

### products (source de vérité après import)
- `id` TEXT PK (stable, issu de `data/products.js`), `brand`, `brand_slug` FK, `name`, `cat` FK, `sub`, `form`, `tint`, `price` INT millimes, `old_price` INT, `size`, `concerns` JSON, `rating`, `reviews`, `stock` (1/0), `featured`, `bestseller`, `image`, `image_alt`, `image_thumb`, `short`, `description`, `ingredients`, `benefits` JSON, `usage_text`, `active`, `track_stock`, `stock_quantity`, `created_at`, `updated_at`
- Index : `brand`, `cat`, `price`, `featured`

### product_variants
- `id` PK, `product_id` FK CASCADE, `sku`, `name`, `price`, `stock_quantity`, `active`

### promotions
- `id` PK, `code` UNIQUE NOCASE, `type` (percentage|fixed), `value` INT, `minimum_order` INT, `maximum_discount` INT, `start_date`, `end_date`, `usage_limit`, `per_user_limit`, `active`, `created_at`, `updated_at`
- Index : `code`

### promotion_usage
- `id` PK, `promotion_id` FK CASCADE, `user_id` FK CASCADE, `order_id` FK CASCADE, `used_at`, UNIQUE(promotion_id, order_id)

### wishlist
- `id` PK, `user_id` FK CASCADE, `product_id` FK CASCADE, `created_at`, UNIQUE(user_id, product_id)
- Index : `user_id`, `product_id`

### orders
- `id` PK, `order_number` UNIQUE (`CLEO-YYYYMMDD-######`), `user_id` FK, `status` (pending|confirmed|preparing|shipped|delivered|cancelled), `subtotal` INT, `discount` INT, `shipping` INT, `total` INT, `payment_method` (cash_on_delivery extensible), `customer_note`, `promo_code`, `promo_discount`, `promotion_id` FK, `shipping_address_json` (snapshot), `shipping_first_name`, `shipping_last_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_postal_code`, `shipping_additional`, `carrier`, `tracking_number`, `shipped_at`, `delivered_at`, `created_at`, `updated_at`
- Index : `user_id`, `order_number`, `status`, `created_at`

### order_items (snapshot historique)
- `id` PK, `order_id` FK CASCADE, `product_id` TEXT, `product_name`, `brand`, `price` INT (au moment de la commande), `quantity`, `image`
- Index : `order_id`

### order_tracking
- `id` PK, `order_id` FK CASCADE, `status`, `note`, `created_at`, `created_by` FK

### settings
- `key` PK, `value`, `updated_at` — clés : `store_name`, `store_phone`, `store_email`, `free_shipping_threshold`, `default_shipping_cost`, `currency`, `order_prefix`, `schema_version`

### admin_activity_logs
- `id` PK, `admin_id` FK, `action`, `target_type`, `target_id`, `metadata` JSON, `created_at`

### contact_messages
- `id` PK, `name`, `email`, `phone`, `subject`, `message`, `status` (new|read|resolved), `created_at`

### password_resets / email_verifications
- `id` PK, `user_id` FK CASCADE, `token_hash` (SHA256), `expires_at`, `used`, `created_at`

### rate_limits
- `id` PK, `bucket` (clé + IP), `created_at` — utilisé pour throttling

### schema_migrations
- `version` PK, `applied_at`

## Argent

Tous les montants en **millimes entiers** (`99000 = 99 DT`). Jamais de `FLOAT`. `calculateOrderTotals()` côté serveur.

## Livraison

`calculateShipping(subtotal)` → `0` si `subtotal >= free_shipping_threshold` sinon `default_shipping_cost`. Seuil par défaut 99000, coût 8000, configurables via `settings` et `config.php`.

## Relations & intégrité

- `PRAGMA foreign_keys=ON`, `ON DELETE CASCADE` où pertinent, `UNIQUE` sur `users.email`, `wishlist`, `promotion_usage`.
- Transactions sur `checkout` (BEGIN/COMMIT/ROLLBACK) pour éviter commandes partielles, prévenir oversell (vérif `track_stock`).
- `order_items` stocke snapshot (nom, prix, image) pour immutabilité.
- `order_tracking` ajoute une ligne à chaque changement de statut.

## Synchronisation catalogue

`data/products.js` → `database/catalog.json` (via `node database/import-catalog.js`) → `php database/import-catalog.php` (UPSERT `ON CONFLICT DO UPDATE`). IDs stables, pas de régénération. `brand_slug` null si marque absente (ex: Bielenda) pour éviter FK violation.

## Sauvegarde

```bash
# à l’arrêt ou via SQLite backup API
sqlite3 database/cleopatre.sqlite ".backup database/backup.sqlite"
# ou copie fichier quand aucun WAL actif (après checkpoint)
sqlite3 database/cleopatre.sqlite "PRAGMA wal_checkpoint(TRUNCATE);"
cp database/cleopatre.sqlite backup/
```
En production, sauvegarder le fichier `cleopatre.sqlite` + `-wal`/`-shm` s’ils existent, ou utiliser `.backup`.

## Migration

`schema_migrations` + `settings.schema_version`. `initSchema()` crée toutes les tables `IF NOT EXISTS` et insère les `settings`/`promotions` de base (`CLEO10`, `BIENVENUE`) avec `INSERT OR IGNORE`.

## Index notables

- `users.email`, `orders.user_id`, `orders.order_number`, `orders.status`, `orders.created_at`, `order_items.order_id`, `wishlist(user_id, product_id)`, `rate_limits(bucket, created_at)`.
