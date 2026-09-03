# CLÉOPÂTRE — Documentation API

Base : `/api` — même origine uniquement, `Content-Type: application/json`, sessions PHP `CLEO_SESS` (HttpOnly, SameSite=Lax).

Toutes les réponses JSON : `{success: bool, message?: string, data?: object, errors?: object}`

## Authentification

### POST /api/auth/register.php
Inscription cliente.
```
{first_name, last_name, email, phone?, password, password_confirm}
```
→ 201 + `{user, csrf_token}` + session créée. Erreurs 422 (validation), 409 (email déjà utilisé), 429 (rate limit).

### POST /api/auth/login.php
```
{email, password}
```
→ 200 + `{user, csrf_token}`. 401 si invalide, 403 si désactivé, 429 si trop de tentatives.

### POST /api/auth/logout.php
→ 200. Supprime session et cookie.

### GET /api/auth/me.php
→ 200 `{authenticated: bool, user: object|null, csrf_token}`. Ne nécessite pas d’auth.

### GET /api/auth/csrf.php
→ 200 `{csrf_token}`.

### POST /api/auth/change-password.php
Authentifié + `X-CSRF-Token` requis.
```
{current_password, new_password, confirm_password}
```
→ 200. 401 si actuel incorrect, 422 si faible.

### POST /api/auth/forgot-password.php
```
{email}
```
→ Toujours 200 (ne révèle pas l’existence du compte). En `app_debug` retourne `debug_token`.

### POST /api/auth/reset-password.php
```
{token, new_password, confirm_password}
```
→ 200. Token SHA256, expire 1h, usage unique.

## Compte

### GET /api/account/profile.php
Authentifié → `{user}`.

### PUT /api/account/profile.php
Auth + CSRF. `{first_name, last_name, email, phone}` → 200.

### GET /api/account/addresses.php
→ `{addresses: []}`.

### POST /api/account/addresses.php
Auth + CSRF. `{label, first_name, last_name, phone, address, city, postal_code, additional_information, is_default}` → 201.

### GET /api/account/address.php?id=ID
→ `{address}` (propriétaire uniquement).

### PUT /api/account/address.php?id=ID
Auth + CSRF → mise à jour.

### DELETE /api/account/address.php?id=ID
Auth + CSRF → suppression. Réassigne défaut si nécessaire.

## Wishlist

### GET /api/wishlist/index.php
Auth → `{wishlist: [{product_id, ...}], count}`.

### POST /api/wishlist/index.php
Auth + CSRF.
- `{product_id, action:"toggle"|"add"|"remove"}` → toggle par défaut
- `{action:"sync", product_ids: []}` → merge guest → serveur (dédup).

## Promotions

### POST /api/promotions/validate.php
```
{code, subtotal?}
```
→ 200 `{promotion: {id, code, type, value, discount_preview}}` ou 404/400.

## Checkout

### POST /api/checkout/create.php
Auth + CSRF. **Jamais faire confiance aux totaux du front** — le serveur recalcule.
```
{
  items: [{id, qty}],
  address_id?: int,
  new_address?: {first_name, last_name, phone, address, city, postal_code, additional_information, save_address, label},
  promo_code?: string,
  payment_method?: "cash_on_delivery",
  customer_note?: string
}
```
→ Transaction atomique : vérifie produits, stock, promo, calcule shipping, crée order + order_items + tracking + promotion_usage + décrémente stock (si track_stock). → 201 `{order, items}`. Erreurs 422/404/409/400.

## Commandes (client)

### GET /api/orders/list.php?page=&per_page=&status=
Auth → `{orders, pagination}`.

### GET /api/orders/detail.php?id= | order_number=
Auth → `{order, items, tracking, shipping_address}`. 404 si n’appartient pas au client (ne révèle pas l’existence).

### POST /api/orders/cancel.php
Auth + CSRF → `{order_id}`. Refus si shipped/delivered/cancelled.

## Catalogue

### GET /api/products/list.php?q=&cat=&brand=&concern=&min=&max=&sort=&page=&per_page=&stock=&featured=
→ `{products, pagination}`. `min/max` en DT (converti en millimes côté serveur).

### GET /api/products/detail.php?id=
→ `{product}`.

### GET /api/categories/list.php
→ `{categories}`.

### GET /api/brands/list.php
→ `{brands}`.

## Contact

### POST /api/contact/submit.php
```
{name, email, phone?, subject?, message}
```
→ 200. Rate limit 5/h.

## Admin (tous exigent session + role=admin + CSRF sur mutations)

### GET /api/admin/dashboard.php
→ `{revenue: {total, today, week, month, rule}, orders: {total, pending, by_status, avg}, customers: {total, new_week}, products: {active}, recent_orders}`.
Règle revenu : `delivered + confirmed + preparing + shipped` (pending/cancelled exclus).

### GET /api/admin/orders.php?q=&status=&from=&to=&page=&per_page=
→ `{orders, pagination}`. Recherche sur order_number, nom, email, téléphone.

### POST /api/admin/orders.php
`{order_id, status, note?}` → progression `pending→confirmed→preparing→shipped→delivered` (étape suivante uniquement) + `cancelled` autorisé sauf si delivered.

### GET /api/admin/customers.php?q=&page=&per_page=&id=
Liste ou détail (`?id=` → `{customer, addresses, orders, stats}`).

### POST /api/admin/customers.php
`{id, action:"disable"|"enable"}`.

### GET /api/admin/products.php?q=&page=&per_page=
→ `{products, pagination}`.

### POST /api/admin/products.php
`{id, active?, featured?, track_stock?, stock_quantity?, stock?, price?}`.

### GET /api/admin/promotions.php
### POST /api/admin/promotions.php
`{code, type:"percentage"|"fixed", value, minimum_order?, maximum_discount?, active?, usage_limit?, per_user_limit?, start_date?, end_date?}`

### PUT /api/admin/promotions.php
Mise à jour partielle + DELETE `?id=` → suppression.

### GET /api/admin/settings.php
→ `{settings: {key: value}}`.

### POST /api/admin/settings.php
`{store_name?, store_phone?, store_email?, free_shipping_threshold?, default_shipping_cost?, currency?, order_prefix?}`

### GET /api/admin/activity.php?page=&per_page=
→ `{logs, pagination}`.

### GET /api/admin/contact.php?status=&page=
→ `{messages, pagination}`.
### POST /api/admin/contact.php
`{id, status:"new"|"read"|"resolved"}`

### POST /api/system/setup.php
`{secret|setup_secret, first_name?, last_name?, email, password}` → création admin initiale (secret = `config.setup_secret`). GET → `{has_admin, has_products}`.

### GET /api/system/health.php
→ `{success, status:"ok"|"error", database:"connected", checks: {}}`. 500 si DB indisponible.

## Sécurité

- Toutes les requêtes mutantes exigent `X-CSRF-Token: <csrf_token>` (valeur de `/api/auth/csrf.php` ou `me.php`). `SameSite=Lax` + `HttpOnly`.
- `PDO` requêtes préparées partout, pas de concaténation.
- Validation stricte côté serveur (email, téléphone tunisien, mots de passe 8+ avec lettre + chiffre/symbole).
- `password_hash` / `password_verify` (bcrypt).
- `session_regenerate_id(true)` à login/register/password-change.
- Rate limiting (table `rate_limits`) sur login/register/forgot/contact.
- IDOR : chaque commande/adresse/wishlist vérifie `user_id`.
- Admin : `requireAdmin()` vérifie session + `status=active` + `role=admin` à chaque requête.

## Codes HTTP

- 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 403 Forbidden (CSRF/role), 404 Not Found, 409 Conflict, 422 Validation, 429 Rate Limit, 500 Erreur serveur (message générique en prod, détail en `app_debug`).

## Exemple curl

```bash
# register
curl -c cookies.txt -b cookies.txt -H "Content-Type: application/json" \
  -d '{"first_name":"Leila","last_name":"Ben Salah","email":"leila@example.com","phone":"+216 22 345 678","password":"Leila1234!","password_confirm":"Leila1234!"}' \
  http://localhost:8000/api/auth/register.php

# me + csrf
curl -b cookies.txt http://localhost:8000/api/auth/me.php
```
