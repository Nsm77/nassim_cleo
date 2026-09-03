# CLÉOPÂTRE — Parapharmacie d’exigence · Tunis

**Statique → Ecommerce complet : PHP 8 + PDO SQLite + Vanilla JS**

Reconstruction intégrale de **para-cleopatre.tn** en plateforme ecommerce production : catalogue → panier → checkout transactionnel → commandes → espace client → administration, sans framework.

> **Design** : le storefront existant (HTML5/CSS3/Vanilla ES6+, `localStorage`, assets) est strictement préservé. Les nouvelles zones (compte, checkout, admin) reprennent le langage Cléopâtre (ivoire `#F5F1E9`, vert `#24301C`, or `#A88F62`, Cormorant × Jost).

---

## 1) Lancer en local (5 min)

**Prérequis** : PHP 8.0+ avec `pdo_sqlite`, `mbstring`, `openssl`. Aucun composer.

```bash
# depuis la racine cleopatre/
php -S localhost:8000
# ouvrir http://localhost:8000
# santé : http://localhost:8000/api/system/health.php  → {"success":true,"status":"ok","database":"connected"}
```

Ou via XAMPP/WAMP : placer le dossier dans `htdocs`, activer `pdo_sqlite`.

**Première installation :**

```bash
# 1) (re)générer le JSON catalogue depuis les JS existants
node database/import-catalog.js

# 2) créer les tables + importer 30 produits / 16 marques / 6 catégories
php database/import-catalog.php
# → Import réussi: 16 marques, 6 catégories, 30 produits

# 3) vérifier
curl http://localhost:8000/api/system/health.php
curl "http://localhost:8000/api/products/list.php?per_page=1" | jq
```

**Créer le premier administrateur (secret dans `config/config.php` → `setup_secret`) :**

```bash
# GET statut
curl http://localhost:8000/api/system/setup.php

# POST création (exemple)
curl -H "Content-Type: application/json" \
  -d '{"secret":"cleo-setup-2026-change-in-prod","first_name":"Admin","last_name":"Cléopâtre","email":"admin@para-cleopatre.tn","password":"Admin1234!"}' \
  http://localhost:8000/api/system/setup.php
```

> Après création, désactivez ou changez `setup_secret` en prod.

**Comptes de démonstration (après création) :**

- Client : inscrivez-vous via `/pages/register.html`, ou utilisez `testreg@example.com / Test1234!` (créé lors des tests)
- Admin : `admin@para-cleopatre.tn / Admin1234!` (ou celui que vous venez de créer)

---

## 2) Architecture

```
cleopatre/
├── index.html                 → inchangé
├── pages/                     → 14 pages existantes + nouvelles :
│   ├── login.html, register.html, forgot.html, reset.html
│   ├── account.html           (Vue d’ensemble, commandes, infos, adresses, envies, sécurité)
│   ├── checkout.html          (Livraison → Paiement → Vérification)
│   ├── confirmation.html      (CLEO-YYYYMMDD-######)
│   ├── order.html             (Détail + timeline suivi)
│   ├── cart.html, wishlist.html, product.html … (préservés, branchés API)
├── admin/                     → back-office Cléopâtre
│   ├── login.html, index.html (dashboard), orders.html, order.html,
│   │   customers.html, customer.html, products.html, promotions.html,
│   │   settings.html, activity.html, contact.html
├── css/global.css + home.css … → inchangés
│   ├── auth.css               (login/register/compte/checkout)
│   └── admin.css              (dashboard sobre, pas Bootstrap)
├── js/
│   ├── global.js              → préservé, menu compte adapté
│   ├── api.js                 → client centralisé (GET/POST/PUT/DELETE, CSRF, 401/403/422)
│   ├── auth.js                → état global, header Mon compte/Administration, merge wishlist
│   └── cart.js, contact.js …  → branchés API (promo serveur, checkout réel)
├── data/products.js, brands.js, categories.js → source catalogue (ne pas dupliquer)
├── api/
│   ├── _bootstrap.php         → config, session HttpOnly SameSite=Lax, PDO WAL, helpers
│   ├── _schema.sql            → schéma complet
│   ├── auth/                  → register, login, logout, me, csrf, change-password, forgot, reset
│   ├── account/               → profile, addresses
│   ├── wishlist/, cart/, checkout/, orders/, products/, categories/, brands/, promotions/, contact/
│   ├── admin/                 → dashboard, orders, customers, products, promotions, settings, activity, contact
│   └── system/                → health, setup
├── config/
│   ├── config.example.php
│   └── config.php             → (à ne pas committer en prod)
├── database/
│   ├── cleopatre.sqlite       → WAL, foreign_keys=ON (protégé .htaccess)
│   ├── catalog.json           → export Node
│   └── import-catalog.{js,php}
├── docs/
│   ├── API.md                 → tous les endpoints
│   └── DATABASE.md            → tables, relations, argent, livraison, sauvegarde
├── storage/ logs/             → (writable)
└── .htaccess, robots.txt, sitemap.xml
```

Flux : `HTML → js/api.js → PHP REST (PDO) → SQLite` (jamais de prix/totaux du front).

---

## 3) Base de données

- **Fichier** : `database/cleopatre.sqlite` (WAL). Jamais téléchargeable (`.htaccess` `Require all denied`).
- **Tables** : `users, user_addresses, sessions, categories, subcategories, brands, products, product_variants, promotions, promotion_usage, wishlist, orders, order_items, order_tracking, settings, admin_activity_logs, contact_messages, password_resets, email_verifications, rate_limits, schema_migrations` (voir `docs/DATABASE.md`).
- **Argent** : entiers millimes (`99000 = 99 DT`), pas de float.
- **Livraison** : `calculateShipping()` → offerte dès `99 DT` (configurable `free_shipping_threshold` / `default_shipping_cost`).
- **Stock** : `track_stock` flag ; si `false` (catalogue actuel sans stock connu) on n’invente pas d’inventaire.
- **Import** : `products.js` → `catalog.json` → `INSERT ... ON CONFLICT DO UPDATE` (IDs stables).
- **Commande** : `order_items` snapshot (nom/prix/image) + `order_tracking` timeline.

---

## 4) Sécurité

- Sessions PHP `session_regenerate_id(true)`, `HttpOnly`, `SameSite=Lax`, `Secure` si HTTPS.
- `password_hash` / `password_verify`, jamais en clair.
- CSRF `X-CSRF-Token` sur toutes les mutations (token dans `/api/auth/csrf.php` & `me.php`).
- `PDO` préparées partout, validation stricte (email, téléphone TN, mots de passe 8+ avec lettre + chiffre/symbole).
- IDOR : chaque commande/adresse/wishlist vérifie `user_id` (403/404).
- Admin : `requireAdmin()` vérifie `role=admin` à chaque requête.
- Rate limiting (`rate_limits`) sur login/register/forgot/contact.
- XSS : `C.esc()` + pas d’`innerHTML` sur données utilisateur non échappées.
- Headers : `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options`.
- Pas de `CORS *`, même origine.

---

## 5) Parcours client

`Accueil → Boutique/Filtres → Produit → Panier (localStorage, guest) → Connexion/Inscription (merge wishlist/cart) → Livraison (adresse enregistrée ou nouvelle) → Paiement à la livraison (extensible) → Vérification (totaux recalculés serveur) → Confirmation `CLEO-…` → Suivi (pending→confirmed→preparing→shipped→delivered, timeline datée) → Espace client (Vue d’ensemble, Mes commandes, Mes informations, Mes adresses, Ma liste d’envies, Sécurité)`.

Invités : navigation, recherche, panier, wishlist en `localStorage` sans compte.

---

## 6) Administration

`admin/login.html` → `admin/index.html` (dashboard : CA total/aujourd’hui/semaine/mois, commandes, panier moyen, clients, produits, dernières commandes, répartition statuts, santé) → `orders.html` (recherche par n°/client/email/téléphone, filtre statut/date, pagination, export CSV) → `order.html` (produits, adresse, paiement, promo, timeline, bouton Étape suivante) → `customers.html`/`customer.html` (commandes, dépensé, wishlist, désactiver/réactiver) → `products.html` (actif/mise en avant/stock/prix) → `promotions.html` (percentage/fixed, min, max, limites) → `settings.html` (seuil livraison, frais, préfixe, contacts) → `activity.html` → `contact.html` (inbox).

Tous les changements admin loggués dans `admin_activity_logs`.

---

## 7) API

Voir `docs/API.md` (auth, account, wishlist, promotions, checkout, orders, products, admin, system, contact). Exemple :

```bash
curl -c jar -b jar -H "Content-Type: application/json" -d '{"email":"a@ex.com","password":"Aa123456!"}' http://localhost:8000/api/auth/login.php
curl -b jar http://localhost:8000/api/auth/me.php
```

---

## 8) Déploiement (Apache/Nginx, Wasmer, hosting PHP)

- **PHP** 8.0+ (recommandé 8.2+), extensions `pdo_sqlite`, `openssl`, `mbstring`, `json`.
- **Document root** : `cleopatre/` (ou `public/` si vous déplacez `database/` hors du webroot — recommandé : mettre `cleopatre.sqlite` dans `../data` non servi).
- **Permissions** : `database/`, `storage/`, `logs/` en `755` + writable par PHP ; `.htaccess` bloque déjà `database/*.sqlite`.
- **HTTPS** : activer `Secure` cookies (auto-détecté via `$_SERVER['HTTPS']`).
- **Prod** : `config.php` → `'app_env'=>'production','app_debug'=>false`, changer `setup_secret`, `display_errors Off`, configurer `mail` si SMTP, `php -S` remplacé par Apache/Nginx + PHP-FPM.
- **Wasmer** : déclarer runtime PHP, document root `cleopatre/`, volume persistant pour `database/cleopatre.sqlite`, variables d’env pour `setup_secret` si besoin.
- **Sauvegarde** : `sqlite3 database/cleopatre.sqlite ".backup backup.sqlite"` ou `cp` après `PRAGMA wal_checkpoint(TRUNCATE);`.
- **Mise à jour catalogue** : relancer `node database/import-catalog.js && php database/import-catalog.php` (UPSERT, pas de DROP).

---

## 9) Tests

Matrice couverte (via `curl` + UI) :

- Inscription (valide, doublon, email invalide, mdp faible, mismatch) ; connexion (ok, mauvais mdp, inconnu, désactivé) ; session (refresh, logout, expirée) ; wishlist (guest, login merge, add/remove) ; panier (guest, login merge, quantité) ; checkout (normal, adresse manquante, produit invalide, promo, livraison offerte, double-clic, rollback) ; commandes (create, list, detail, tracking, IDOR) ; admin (login, rejet client, dashboard, orders/status, customers, products, settings, activity) ; sécurité (SQLi, XSS, CSRF, IDOR, fixation) — voir `api/_bootstrap.php` `requireAdmin`, `requireCsrf`, requêtes préparées, `esc`.

**Test d’intégrité commande (end-to-end) :**
```bash
php database/import-catalog.php
curl -X POST -d '{"secret":"...","email":"admin@...","password":"..."}' http://localhost:8000/api/system/setup.php
# client : register → login → POST /api/account/addresses.php → POST /api/checkout/create.php → GET /api/orders/detail.php
# admin : POST /api/admin/orders.php {status:confirmed} → client re-GET → timeline ok
```

Health : `GET /api/system/health.php` → `{"success":true,"status":"ok","database":"connected"}` (500 si indisponible).

---

## 10) Limitations connues

- Pas d’envoi d’e-mail réel (architecture `password_resets`/`email_verifications` + `config.mail` prête, `mail.enabled=false` → log en dev). Ne pas exposer `debug_token` en prod.
- Paiement uniquement `cash_on_delivery` (abstraction `payment_method` prête pour carte/virement).
- Stock `track_stock=false` par défaut (données catalogue sans inventaire) — ne pas sur-vendre si activé, transactionnel.
- Pas d’upload d’images admin (validation stricte prévue si ajouté).
- SQLite WAL : sauvegarder à chaud via `.backup`, pas par simple `cp` si `-wal` présent.

---

## 11) Licence & crédits

Contenu métier Cléopâtre conservé (livraison 99 DT, +216 29 835 402, etc.). Prix fictifs démonstration. Design system Cléopâtre (ivoire/charbon/vert/or, Cormorant/Garamond × Jost) préservé.
