<?php
// CLÉOPÂTRE — Bootstrap central
// Inclus par tous les endpoints API
declare(strict_types=1);

$__root = dirname(__DIR__);
$configPath = $__root . '/config/config.php';
$fallback = $__root . '/config/config.example.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $config = require $fallback;
}

if (!isset($config['db_path'])) $config['db_path'] = $__root . '/database/cleopatre.sqlite';
if (!isset($config['app_env'])) $config['app_env'] = 'development';
if (!isset($config['app_debug'])) $config['app_debug'] = ($config['app_env'] !== 'production');

if ($config['app_debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Headers sécurité de base (non bloquant)
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Frame-Options: SAMEORIGIN');

// Session sécurisée — base-path aware pour sous-dossier (/ /cleopatre/)
$sessionName = $config['session_name'] ?? 'CLEO_SESS';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$lifetime = $config['session_lifetime'] ?? 86400*7;
// Détecte le chemin application (ex: /cleopatre) pour cookie path
$__scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$__basePath = '/';
if (strpos($__scriptDir, '/api') !== false) {
    // script dans /api/... -> base est un niveau au-dessus de /api
    $__basePath = substr($__scriptDir, 0, strpos($__scriptDir, '/api'));
    if ($__basePath === '') $__basePath = '/';
}
if (!isset($config['session_path'])) {
    $config['session_path'] = ($__basePath === '' ? '/' : rtrim($__basePath, '/') . '/');
}
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $config['session_path'],
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

// CORS same-origin only
// Pas de Access-Control-Allow-Origin: *

// Helper : PDO singleton
function db(): PDO {
    static $pdo = null;
    global $config, $__root;
    if ($pdo !== null) return $pdo;
    $dbPath = $config['db_path'];
    $dir = dirname($dbPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $needInit = !file_exists($dbPath);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    if ($needInit || needsSchema($pdo)) {
        initSchema($pdo);
    } else {
        // ensure migrations for existing DB
        try { runMigrations($pdo); } catch(Throwable $e) {}
        // ensure default rewards exist
        try {
            $cnt = $pdo->query("SELECT COUNT(*) FROM loyalty_rewards")->fetchColumn();
            if((int)$cnt===0){
                $pdo->prepare("INSERT OR IGNORE INTO loyalty_rewards(code, name, points_cost, discount_value, active) VALUES (?, ?, ?, ?, ?)")->execute(['REWARD10','Bon d’achat 10 DT',1000,10000,1]);
                $pdo->prepare("INSERT OR IGNORE INTO loyalty_rewards(code, name, points_cost, discount_value, active) VALUES (?, ?, ?, ?, ?)")->execute(['REWARD20','Bon d’achat 20 DT',2000,20000,1]);
            }
        } catch(Throwable $e){}
        try { $pdo->exec("INSERT OR IGNORE INTO loyalty_accounts(user_id, balance) SELECT id, 0 FROM users WHERE role='customer'"); } catch(Throwable $e){}
    }
    return $pdo;
}

function needsSchema(PDO $pdo): bool {
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        return false;
    } catch (Throwable $e) {
        return true;
    }
}

function initSchema(PDO $pdo): void {
    $sql = file_get_contents(__DIR__ . '/_schema.sql');
    if (!$sql) {
        // fallback inline si fichier absent (sera créé juste après)
        $sql = getInlineSchema();
    }
    $pdo->exec($sql);
    // seed settings par défaut
    $defaults = [
        'store_name' => 'Cléopâtre',
        'store_phone' => '+216 29 835 402',
        'store_email' => 'cleopatreparapharmacie@gmail.com',
        'free_shipping_threshold' => '99000',
        'default_shipping_cost' => '8000',
        'currency' => 'DT',
        'order_prefix' => 'CLEO',
        'schema_version' => '2',
        'loyalty_enabled' => '1',
        'loyalty_rate' => '10',
        'loyalty_reward_threshold' => '1000',
        'loyalty_reward_value' => '10000',
    ];
    foreach ($defaults as $k => $v) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings(key, value) VALUES (?, ?)");
        $stmt->execute([$k, $v]);
    }
    // seed promotions de démo (codes existants côté front)
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO promotions(code, type, value, minimum_order, maximum_discount, active) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['CLEO10', 'percentage', 10, 0, null, 1]);
    $stmt->execute(['BIENVENUE', 'percentage', 5, 0, null, 1]);
    // seed loyalty reward par défaut : 1000 pts = 10 DT
    try {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO loyalty_rewards(code, name, points_cost, discount_value, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['REWARD10', 'Bon d’achat 10 DT', 1000, 10000, 1]);
        $stmt->execute(['REWARD20', 'Bon d’achat 20 DT', 2000, 20000, 1]);
    } catch(Throwable $e) {}
    // ensure loyalty_accounts for existing users
    try { $pdo->exec("INSERT OR IGNORE INTO loyalty_accounts(user_id, balance) SELECT id, 0 FROM users WHERE role='customer'"); } catch(Throwable $e) {}
    // run lightweight migrations for existing DB (columns/tables that may be missing)
    runMigrations($pdo);
}

function runMigrations(PDO $pdo): void {
    // add suspended status support: ensure users status check allows suspended (recreate if needed - we just handle at app level)
    // ensure column additions without failing if exists
    $checks = [
        "CREATE TABLE IF NOT EXISTS loyalty_accounts (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, balance INTEGER NOT NULL DEFAULT 0, lifetime_earned INTEGER NOT NULL DEFAULT 0, lifetime_redeemed INTEGER NOT NULL DEFAULT 0, tier TEXT NOT NULL DEFAULT 'classic', updated_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS loyalty_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL, type TEXT NOT NULL, points INTEGER NOT NULL, balance_after INTEGER NOT NULL, reference TEXT, created_by INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS loyalty_rewards (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT NOT NULL, points_cost INTEGER NOT NULL, discount_value INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1, usage_limit INTEGER, per_user_limit INTEGER, start_date TEXT, end_date TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id TEXT NOT NULL, user_id INTEGER NOT NULL, order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL, rating INTEGER NOT NULL, title TEXT, body TEXT, verified_purchase INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'pending', helpful_count INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS support_tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_number TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL, order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL, subject TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'open', priority TEXT NOT NULL DEFAULT 'normal', created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS support_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id INTEGER NOT NULL, user_id INTEGER NOT NULL, message TEXT NOT NULL, is_internal INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, body TEXT, link TEXT, is_read INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS recently_viewed (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id TEXT NOT NULL, viewed_at TEXT NOT NULL DEFAULT (datetime('now')), UNIQUE(user_id, product_id))",
        "CREATE TABLE IF NOT EXISTS inventory_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id TEXT NOT NULL, change_qty INTEGER NOT NULL, reason TEXT NOT NULL, order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL, admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
    ];
    foreach($checks as $sql) { try { $pdo->exec($sql); } catch(Throwable $e) {} }
    // ==========================================================
    // CLÉOPÂTRE — EXTENDED ECOMMERCE TABLES
    // ==========================================================
    $extended = [
        "CREATE TABLE IF NOT EXISTS customer_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL, note TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS customer_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, tag TEXT NOT NULL, color TEXT, created_by INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')), UNIQUE(user_id, tag))",
        "CREATE TABLE IF NOT EXISTS collections (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT NOT NULL, description TEXT, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS collection_products (collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE, product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE, sort_order INTEGER NOT NULL DEFAULT 0, added_at TEXT NOT NULL DEFAULT (datetime('now')), PRIMARY KEY(collection_id, product_id))",
        "CREATE TABLE IF NOT EXISTS loyalty_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, type TEXT NOT NULL DEFAULT 'double_points', multiplier REAL NOT NULL DEFAULT 2, bonus_points INTEGER NOT NULL DEFAULT 0, start_date TEXT, end_date TEXT, active INTEGER NOT NULL DEFAULT 1, conditions_json TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS flash_sales (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, discount_type TEXT NOT NULL DEFAULT 'percentage', discount_value INTEGER NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_by INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS flash_sale_products (flash_sale_id INTEGER NOT NULL REFERENCES flash_sales(id) ON DELETE CASCADE, product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE, PRIMARY KEY(flash_sale_id, product_id))",
        "CREATE TABLE IF NOT EXISTS order_internal_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE, admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL, note TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS product_collections_cache (slot_key TEXT PRIMARY KEY, product_ids TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))",
        "CREATE TABLE IF NOT EXISTS admin_invites (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, role TEXT NOT NULL, invited_by INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')))",
    ];
    foreach($extended as $sql) { try { $pdo->exec($sql); } catch(Throwable $e) {} }
    // inventory_movements: ensure extra columns for history detail
    try {
        $cols = $pdo->query("PRAGMA table_info(inventory_movements)")->fetchAll();
        $names = array_column($cols,'name');
        if(!in_array('previous_qty',$names)) { $pdo->exec("ALTER TABLE inventory_movements ADD COLUMN previous_qty INTEGER"); }
        if(!in_array('new_qty',$names)) { $pdo->exec("ALTER TABLE inventory_movements ADD COLUMN new_qty INTEGER"); }
        if(!in_array('reference',$names)) { $pdo->exec("ALTER TABLE inventory_movements ADD COLUMN reference TEXT"); }
    } catch(Throwable $e) {}
    // products: ensure extra merchandising / status columns
    try {
        $pcols = $pdo->query("PRAGMA table_info(products)")->fetchAll();
        $pnames = array_column($pcols,'name');
        if(!in_array('is_new',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN is_new INTEGER NOT NULL DEFAULT 0"); }
        if(!in_array('promo_active',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN promo_active INTEGER NOT NULL DEFAULT 0"); }
        if(!in_array('promo_discount_type',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN promo_discount_type TEXT"); }
        if(!in_array('promo_discount_value',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN promo_discount_value INTEGER"); }
        if(!in_array('promo_start',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN promo_start TEXT"); }
        if(!in_array('promo_end',$pnames)) { $pdo->exec("ALTER TABLE products ADD COLUMN promo_end TEXT"); }
    } catch(Throwable $e) {}
    // promotions: ensure scope columns for category/brand/product
    try {
        $prcols = $pdo->query("PRAGMA table_info(promotions)")->fetchAll();
        $prnames = array_column($prcols,'name');
        if(!in_array('scope_type',$prnames)) { $pdo->exec("ALTER TABLE promotions ADD COLUMN scope_type TEXT DEFAULT 'cart'"); }
        if(!in_array('scope_value',$prnames)) { $pdo->exec("ALTER TABLE promotions ADD COLUMN scope_value TEXT"); }
        if(!in_array('customer_eligibility',$prnames)) { $pdo->exec("ALTER TABLE promotions ADD COLUMN customer_eligibility TEXT"); }
        if(!in_array('loyalty_tier',$prnames)) { $pdo->exec("ALTER TABLE promotions ADD COLUMN loyalty_tier TEXT"); }
    } catch(Throwable $e) {}
    // ensure default collections
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
        if((int)$cnt===0){
            $pdo->prepare("INSERT OR IGNORE INTO collections(slug, name, description, active, sort_order) VALUES (?,?,?,?,?)")->execute(['routine-hydratation','Routine Hydratation','Sérums + crèmes + SPF pour peaux déshydratées',1,1]);
            $pdo->prepare("INSERT OR IGNORE INTO collections(slug, name, description, active, sort_order) VALUES (?,?,?,?,?)")->execute(['essentiels-spf','Essentiels SPF','Les solaires indispensables de la saison',1,2]);
            $pdo->prepare("INSERT OR IGNORE INTO collections(slug, name, description, active, sort_order) VALUES (?,?,?,?,?)")->execute(['incontournables','Les incontournables','Best-sellers plébiscités par nos clientes',1,3]);
        }
    } catch(Throwable $e) {}
    // loyalty columns on orders (for historic price protection + loyalty)
    $orderCols = $pdo->query("PRAGMA table_info(orders)")->fetchAll();
    $colNames = array_column($orderCols,'name');
    if(!in_array('loyalty_discount',$colNames)){ try{ $pdo->exec("ALTER TABLE orders ADD COLUMN loyalty_discount INTEGER NOT NULL DEFAULT 0"); }catch(Throwable $e){} }
    if(!in_array('loyalty_points_used',$colNames)){ try{ $pdo->exec("ALTER TABLE orders ADD COLUMN loyalty_points_used INTEGER NOT NULL DEFAULT 0"); }catch(Throwable $e){} }
    if(!in_array('loyalty_points_earned',$colNames)){ try{ $pdo->exec("ALTER TABLE orders ADD COLUMN loyalty_points_earned INTEGER NOT NULL DEFAULT 0"); }catch(Throwable $e){} }
    if(!in_array('loyalty_reward_id',$colNames)){ try{ $pdo->exec("ALTER TABLE orders ADD COLUMN loyalty_reward_id INTEGER REFERENCES loyalty_rewards(id) ON DELETE SET NULL"); }catch(Throwable $e){} }
    // ensure notification_preferences table
    try{ $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, order_updates INTEGER NOT NULL DEFAULT 1, loyalty_updates INTEGER NOT NULL DEFAULT 1, promotions INTEGER NOT NULL DEFAULT 1, stock_alerts INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL DEFAULT (datetime('now')))"); }catch(Throwable $e){}
    // fix users status/role check to allow suspended and admin roles
    try{
      $sqlRow=$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
      $needsFix = false;
      if($sqlRow){
        if(strpos($sqlRow,'suspended')===false) $needsFix=true;
        if(strpos($sqlRow,'super_admin')===false) $needsFix=true;
      }
      if($needsFix){
        $pdo->exec("PRAGMA foreign_keys=OFF");
        $pdo->exec("CREATE TABLE IF NOT EXISTS users_new (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, first_name TEXT NOT NULL, last_name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE, phone TEXT, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'customer' CHECK(role IN ('customer','admin','super_admin','manager','staff')), status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended','disabled')), email_verified INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')), last_login_at TEXT)");
        $cnt=$pdo->query("SELECT COUNT(*) FROM users_new")->fetchColumn();
        if((int)$cnt===0){
          $pdo->exec("INSERT OR IGNORE INTO users_new(id, uuid, first_name, last_name, email, phone, password_hash, role, status, email_verified, created_at, updated_at, last_login_at) SELECT id, uuid, first_name, last_name, email, phone, password_hash, role, status, email_verified, created_at, updated_at, last_login_at FROM users");
          $pdo->exec("DROP TABLE users");
          $pdo->exec("ALTER TABLE users_new RENAME TO users");
          $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
          $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)");
          $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)");
        } else {
          $pdo->exec("DROP TABLE IF EXISTS users_new");
        }
        $pdo->exec("PRAGMA foreign_keys=ON");
      }
    }catch(Throwable $e){ try{$pdo->exec("PRAGMA foreign_keys=ON");}catch(Throwable $ee){} }
    // ensure indexes
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_loyalty_tx_user ON loyalty_transactions(user_id)"); } catch(Throwable $e){}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id)"); } catch(Throwable $e){}
    // ensure default nssm admin exists (development/default requirement: nssm / nssm)
    try { ensureDefaultAdmin($pdo); } catch(Throwable $e) {}
}

function ensureDefaultAdmin(PDO $pdo): void {
    $exists = $pdo->prepare("SELECT id FROM users WHERE email IN ('nssm','nssm@cleopatre.tn','nssm@cleopatre.local') COLLATE NOCASE LIMIT 1");
    $exists->execute();
    if ($exists->fetch()) return;
    $hash = password_hash('nssm', PASSWORD_DEFAULT);
    $uuid = generateUuid();
    try {
        $pdo->prepare("INSERT INTO users(uuid, first_name, last_name, email, phone, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', ?, ?)")
            ->execute([$uuid, 'NSSM', 'Admin', 'nssm', '', $hash, now(), now()]);
    } catch(Throwable $e) {}
    $check = $pdo->prepare("SELECT id FROM users WHERE email=? COLLATE NOCASE LIMIT 1");
    $check->execute(['nssm@cleopatre.tn']);
    if (!$check->fetch()) {
        try {
            $pdo->prepare("INSERT INTO users(uuid, first_name, last_name, email, phone, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', ?, ?)")
                ->execute([generateUuid(), 'NSSM', 'Admin', 'nssm@cleopatre.tn', '', $hash, now(), now()]);
        } catch(Throwable $e) {}
    }
}

function getInlineSchema(): string {
    return <<<SQL
-- fallback schema minimal
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'customer' CHECK(role IN ('customer','admin')),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','disabled')),
    email_verified INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    last_login_at TEXT
);
SQL;
}

// Réponses JSON
function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function jsonError(string $message, int $code = 400, $errors = null): void {
    $payload = ['success' => false, 'message' => $message];
    if ($errors !== null) $payload['errors'] = $errors;
    jsonResponse($payload, $code);
}
function jsonSuccess($data = null, string $message = null, int $code = 200): void {
    $payload = ['success' => true];
    if ($message !== null) $payload['message'] = $message;
    if ($data !== null) $payload['data'] = $data;
    jsonResponse($payload, $code);
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function input(string $key, $default = null) {
    $json = getJsonInput();
    if (isset($json[$key])) return $json[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($_GET[$key])) return $_GET[$key];
    return $default;
}

// Validation
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254;
}
function validatePassword(string $pwd, ?string &$msg = null): bool {
    if (strlen($pwd) < 8) { $msg = 'Le mot de passe doit contenir au moins 8 caractères.'; return false; }
    if (strlen($pwd) > 128) { $msg = 'Le mot de passe est trop long.'; return false; }
    // au moins 1 lettre et 1 chiffre ou symbole pour éviter mots trop faibles
    if (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9\W]/', $pwd)) {
        $msg = 'Le mot de passe doit contenir au moins une lettre et un chiffre ou symbole.';
        return false;
    }
    return true;
}
function validatePhone(?string $phone): bool {
    if ($phone === null || $phone === '') return true; // optionnel
    // Tunisie : 8 chiffres, peut commencer par +216, espaces/tirets autorisés
    $clean = preg_replace('/[\s\-\.\(\)]/', '', $phone);
    if (preg_match('/^\+216[0-9]{8}$/', $clean)) return true;
    if (preg_match('/^[0-9]{8}$/', $clean)) return true;
    if (preg_match('/^\+?[0-9]{8,15}$/', $clean)) return true;
    return false;
}
function sanitizeString(?string $s, int $max = 255): string {
    if ($s === null) return '';
    $s = trim($s);
    // Strip HTML tags to prevent stored XSS — frontend must still esc() on output
    $s = strip_tags($s);
    // Remove control chars except whitespace
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s);
    if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
    return $s;
}

// Auth helpers
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if (!$u) { session_regenerate_id(true); $_SESSION = []; return null; }
        if ($u['status'] !== 'active') { return null; }
        return $u;
    } catch (Throwable $e) {
        return null;
    }
}
function requireAuth(): array {
    $u = currentUser();
    if (!$u) jsonError('Authentification requise. Veuillez vous connecter.', 401);
    return $u;
}
function requireAdmin(): array {
    $u = requireAuth();
    $allowed = ['admin','super_admin'];
    if (!in_array($u['role'], $allowed, true)) jsonError('Accès réservé à l’administration.', 403);
    return $u;
}
function requireSuperAdmin(): array {
    $u = requireAuth();
    if ($u['role'] !== 'super_admin') jsonError('Accès réservé au Super Admin.', 403);
    return $u;
}
function requireProductAdmin(): array {
    // Normal admin + super_admin can manage products (PRODUCT MGMT ONLY for normal admin)
    $u = requireAdmin();
    return $u;
}
function isAdmin(?array $user = null): bool {
    $u = $user ?? currentUser();
    return $u && in_array($u['role'], ['admin','super_admin'], true);
}
function isSuperAdmin(?array $user = null): bool {
    $u = $user ?? currentUser();
    return $u && $u['role'] === 'super_admin';
}
function requireRole(array $roles): array {
    $u = requireAuth();
    if (!in_array($u['role'],$roles)) jsonError('Permission insuffisante.',403);
    return $u;
}
function hasPermission(?array $user, string $perm): bool {
    $u=$user ?? currentUser();
    if(!$u) return false;
    $role=$u['role'];
    // STRICT TWO-LEVEL: super_admin = *, admin = products/inventory/brands/categories only
    $map=[
        'super_admin'=>['*'],
        'admin'=>['products','inventory','brands','categories','collections_read'],
        'customer'=>[]
    ];
    if(!isset($map[$role])) return false;
    if(in_array('*', $map[$role], true)) return true;
    // products permission covers inventory/brands/categories read for assignment
    if ($perm==='inventory' && in_array('products',$map[$role],true)) return true;
    if (in_array($perm, ['brands','categories']) && in_array('products',$map[$role],true)) return true;
    return in_array($perm, $map[$role], true);
}
function enforcePermission(string $perm): void {
    if (!hasPermission(null, $perm)) {
        $u = currentUser();
        appLog('warning','permission denied',['user'=> $u['email']??'unknown','perm'=>$perm,'role'=>$u['role']??'none']);
        jsonError('Permission insuffisante — accès réservé au Super Admin.', 403);
    }
}

// CSRF
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfVerify(?string $token): bool {
    global $config;
    if (empty($config['csrf_enabled'])) return true;
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
function requireCsrf(): void {
    global $config;
    if (empty($config['csrf_enabled'])) return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? null;
    if (!$token) {
        $json = getJsonInput();
        $token = $json['_csrf'] ?? $_POST['_csrf'] ?? null;
    }
    if (!csrfVerify($token)) {
        jsonError('Jeton de sécurité invalide. Veuillez recharger la page.', 403);
    }
}

// Rate limiting simple (table rate_limits)
function rateLimitCheck(string $key, int $max, int $windowSec): bool {
    // retourne true si autorisé, false si bloqué
    try {
        $pdo = db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $bucket = $key . ':' . $ip;
        $now = time();
        $windowStart = date('Y-m-d H:i:s', $now - $windowSec);
        // nettoyer anciens
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?")->execute([$windowStart]);
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM rate_limits WHERE bucket = ? AND created_at >= ?");
        $stmt->execute([$bucket, $windowStart]);
        $c = (int)$stmt->fetchColumn();
        if ($c >= $max) return false;
        $pdo->prepare("INSERT INTO rate_limits(bucket, created_at) VALUES (?, datetime('now'))")->execute([$bucket]);
        return true;
    } catch (Throwable $e) {
        return true; // fail open en cas d'erreur DB
    }
}
function rateLimitOrFail(string $key, int $max, int $windowSec, string $msg = 'Trop de tentatives. Veuillez réessayer plus tard.'): void {
    if (!rateLimitCheck($key, $max, $windowSec)) {
        jsonError($msg, 429);
    }
}

// Money / Shipping
function getSetting(string $key, $default = null) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? $v : $default;
    } catch (Throwable $e) { return $default; }
}
function calculateShipping(int $subtotal): int {
    $threshold = (int)(getSetting('free_shipping_threshold', '99000'));
    $cost = (int)(getSetting('default_shipping_cost', '8000'));
    return $subtotal >= $threshold ? 0 : $cost;
}
function calculateOrderTotals(array $items, ?array $promo = null): array {
    // items: [['price'=>int,'qty'=>int],...]
    $subtotal = 0;
    foreach ($items as $it) $subtotal += (int)$it['price'] * (int)$it['qty'];
    $discount = 0;
    if ($promo) {
        if ($promo['type'] === 'percentage') {
            $discount = (int) round($subtotal * ((int)$promo['value'] / 100));
            if (!empty($promo['maximum_discount'])) $discount = min($discount, (int)$promo['maximum_discount']);
        } elseif ($promo['type'] === 'fixed') {
            $discount = (int)$promo['value'];
            $discount = min($discount, $subtotal);
        }
    }
    $shipping = calculateShipping($subtotal - $discount);
    $total = max(0, $subtotal - $discount + $shipping);
    return compact('subtotal','discount','shipping','total');
}

// Loyalty helpers
function getLoyaltyAccount(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM loyalty_accounts WHERE user_id=?");
    $stmt->execute([$userId]);
    $acc = $stmt->fetch();
    if (!$acc) {
        $pdo->prepare("INSERT OR IGNORE INTO loyalty_accounts(user_id, balance) VALUES (?, 0)")->execute([$userId]);
        $stmt->execute([$userId]);
        $acc = $stmt->fetch();
    }
    return $acc ?: ['user_id'=>$userId,'balance'=>0,'lifetime_earned'=>0,'lifetime_redeemed'=>0,'tier'=>'classic'];
}
function loyaltyAddPoints(int $userId, int $points, string $type, ?int $orderId = null, ?string $reference = null, ?int $createdBy = null): array {
    if ($points === 0) return getLoyaltyAccount($userId);
    $pdo = db();
    $acc = getLoyaltyAccount($userId);
    $newBalance = (int)$acc['balance'] + $points;
    if ($newBalance < 0) $newBalance = 0;
    // update account
    $pdo->prepare("UPDATE loyalty_accounts SET balance=?, lifetime_earned = lifetime_earned + ?, lifetime_redeemed = lifetime_redeemed + ?, updated_at=? WHERE user_id=?")
        ->execute([$newBalance, $points > 0 ? $points : 0, $points < 0 ? abs($points) : 0, now(), $userId]);
    // handle tier promotion (bonus architecture)
    $tier = $acc['tier'];
    $earned = (int)$acc['lifetime_earned'] + max(0,$points);
    if ($earned >= 10000) $tier='platine';
    elseif ($earned >= 5000) $tier='or';
    elseif ($earned >= 2000) $tier='argent';
    else $tier='classic';
    if ($tier !== $acc['tier']) {
        $pdo->prepare("UPDATE loyalty_accounts SET tier=? WHERE user_id=?")->execute([$tier,$userId]);
    }
    $pdo->prepare("INSERT INTO loyalty_transactions(user_id, order_id, type, points, balance_after, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $orderId, $type, $points, $newBalance, $reference, $createdBy]);
    // notification
    try {
        $title = $points > 0 ? "+{$points} points fidélité" : "{$points} points fidélité";
        $body = $type==='earned' ? "Vous avez gagné {$points} points. Merci pour votre commande !" : ($type==='redeemed' ? "Vous avez utilisé ".abs($points)." points (10 DT de réduction)." : "Mise à jour fidélité : {$points} points.");
        $pdo->prepare("INSERT INTO notifications(user_id, type, title, body) VALUES (?, 'loyalty', ?, ?)")->execute([$userId, $title, $body]);
    } catch(Throwable $e) {}
    $acc['balance']=$newBalance;
    $acc['tier']=$tier;
    return $acc;
}
function calculateLoyaltyPoints(int $amountMillimes): int {
    $rate = (int)getSetting('loyalty_rate','10'); // 10 pts per DT
    // amount in DT = millimes /1000 ; points = DT * rate
    return (int) floor(($amountMillimes / 1000) * $rate);
}
function getAvailableLoyaltyRewards(int $userBalance): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM loyalty_rewards WHERE active=1 ORDER BY points_cost ASC");
    $rewards = $stmt->fetchAll();
    foreach($rewards as &$r) { $r['eligible'] = $userBalance >= (int)$r['points_cost']; $r['discount_preview'] = (int)$r['discount_value']; }
    return $rewards;
}
function createNotification(int $userId, string $type, string $title, ?string $body=null, ?string $link=null): void {
    try {
        $pdo = db();
        $pdo->prepare("INSERT INTO notifications(user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)")->execute([$userId,$type,$title,$body,$link]);
    } catch(Throwable $e) {}
}

// UUID & Order number
function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function generateOrderNumber(PDO $pdo): string {
    $prefix = getSetting('order_prefix', 'CLEO');
    $date = date('Ymd');
    // Chercher le compteur du jour
    $like = $prefix . '-' . $date . '-%';
    $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$like]);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last) {
        $parts = explode('-', $last);
        $seq = ((int) end($parts)) + 1;
    }
    return sprintf('%s-%s-%06d', $prefix, $date, $seq);
}

// Logging (sanitized)
function appLog(string $level, string $message, array $context = []): void {
    global $config;
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . '/app.log';
    $line = sprintf("[%s] %s: %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '');
    @file_put_contents($file, $line, FILE_APPEND);
}
function adminLog(int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, $metadata = null): void {
    try {
        $pdo = db();
        $pdo->prepare("INSERT INTO admin_activity_logs(admin_id, action, target_type, target_id, metadata) VALUES (?, ?, ?, ?, ?)")
            ->execute([$adminId, $action, $targetType, $targetId, $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null]);
    } catch (Throwable $e) { appLog('error', 'adminLog failed', ['e'=>$e->getMessage()]); }
}

// Helpers divers
function now(): string { return date('Y-m-d H:i:s'); }
function normalizeEmail(string $email): string { return mb_strtolower(trim($email)); }
function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return '***';
    $name = $parts[0];
    $masked = mb_substr($name,0,2) . str_repeat('*', max(1, mb_strlen($name)-2));
    return $masked . '@' . $parts[1];
}
