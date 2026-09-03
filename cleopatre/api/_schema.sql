PRAGMA foreign_keys=OFF;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'customer' CHECK(role IN ('customer','admin','super_admin','manager','staff')),
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended','disabled')),
    email_verified INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    last_login_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

CREATE TABLE IF NOT EXISTS user_addresses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    label TEXT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT,
    address TEXT NOT NULL,
    city TEXT NOT NULL,
    postal_code TEXT NOT NULL,
    additional_information TEXT,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_addresses_user ON user_addresses(user_id);

CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    payload TEXT,
    last_activity INTEGER,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS categories (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    eyebrow TEXT,
    tagline TEXT,
    description TEXT,
    intro TEXT,
    accent TEXT,
    surface TEXT,
    form TEXT,
    keywords TEXT
);

CREATE TABLE IF NOT EXISTS subcategories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_slug TEXT NOT NULL REFERENCES categories(slug) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    UNIQUE(category_slug, slug)
);

CREATE TABLE IF NOT EXISTS brands (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    country TEXT,
    est TEXT,
    letter TEXT,
    featured INTEGER DEFAULT 0,
    tint TEXT,
    tagline TEXT,
    story TEXT,
    signature TEXT,
    values_json TEXT
);

CREATE TABLE IF NOT EXISTS products (
    id TEXT PRIMARY KEY,
    brand TEXT NOT NULL,
    brand_slug TEXT REFERENCES brands(slug) ON DELETE SET NULL,
    name TEXT NOT NULL,
    cat TEXT NOT NULL REFERENCES categories(slug) ON DELETE SET NULL,
    sub TEXT,
    form TEXT,
    tint TEXT,
    price INTEGER NOT NULL,
    old_price INTEGER,
    size TEXT,
    concerns TEXT,
    rating REAL DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 1,
    featured INTEGER NOT NULL DEFAULT 0,
    bestseller INTEGER,
    image TEXT,
    image_alt TEXT,
    image_thumb TEXT,
    short TEXT,
    description TEXT,
    ingredients TEXT,
    benefits TEXT,
    usage_text TEXT,
    active INTEGER NOT NULL DEFAULT 1,
    track_stock INTEGER NOT NULL DEFAULT 0,
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand);
CREATE INDEX IF NOT EXISTS idx_products_cat ON products(cat);
CREATE INDEX IF NOT EXISTS idx_products_price ON products(price);
CREATE INDEX IF NOT EXISTS idx_products_featured ON products(featured);

CREATE TABLE IF NOT EXISTS product_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku TEXT,
    name TEXT,
    price INTEGER,
    stock_quantity INTEGER DEFAULT 0,
    active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS promotions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE COLLATE NOCASE,
    type TEXT NOT NULL CHECK(type IN ('percentage','fixed')),
    value INTEGER NOT NULL,
    minimum_order INTEGER NOT NULL DEFAULT 0,
    maximum_discount INTEGER,
    start_date TEXT,
    end_date TEXT,
    usage_limit INTEGER,
    per_user_limit INTEGER,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_promotions_code ON promotions(code);

CREATE TABLE IF NOT EXISTS promotion_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    promotion_id INTEGER NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    used_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(promotion_id, order_id)
);
CREATE INDEX IF NOT EXISTS idx_promo_usage_user ON promotion_usage(user_id);
CREATE INDEX IF NOT EXISTS idx_promo_usage_promo ON promotion_usage(promotion_id);

CREATE TABLE IF NOT EXISTS wishlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(user_id, product_id)
);
CREATE INDEX IF NOT EXISTS idx_wishlist_user ON wishlist(user_id);
CREATE INDEX IF NOT EXISTS idx_wishlist_product ON wishlist(product_id);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','preparing','shipped','delivered','cancelled')),
    subtotal INTEGER NOT NULL,
    discount INTEGER NOT NULL DEFAULT 0,
    shipping INTEGER NOT NULL DEFAULT 0,
    total INTEGER NOT NULL,
    payment_method TEXT NOT NULL DEFAULT 'cash_on_delivery',
    customer_note TEXT,
    promo_code TEXT,
    promo_discount INTEGER DEFAULT 0,
    promotion_id INTEGER REFERENCES promotions(id) ON DELETE SET NULL,
    shipping_address_json TEXT,
    shipping_first_name TEXT,
    shipping_last_name TEXT,
    shipping_phone TEXT,
    shipping_address TEXT,
    shipping_city TEXT,
    shipping_postal_code TEXT,
    shipping_additional TEXT,
    carrier TEXT,
    tracking_number TEXT,
    shipped_at TEXT,
    delivered_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id TEXT NOT NULL,
    product_name TEXT NOT NULL,
    brand TEXT,
    price INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    image TEXT,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);

CREATE TABLE IF NOT EXISTS order_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    status TEXT NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_tracking_order ON order_tracking(order_id);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id TEXT,
    metadata TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_admin_logs_admin ON admin_activity_logs(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created ON admin_activity_logs(created_at);

CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    subject TEXT,
    message TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','read','resolved')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_messages(status);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_pwd_reset_user ON password_resets(user_id);

CREATE TABLE IF NOT EXISTS email_verifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_rate_bucket ON rate_limits(bucket);
CREATE INDEX IF NOT EXISTS idx_rate_created ON rate_limits(created_at);

CREATE TABLE IF NOT EXISTS schema_migrations (
    version TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ==========================================================
-- CLÉOPÂTRE — Extensions production (loyauté, avis, support, notifications, etc.)
-- ==========================================================

CREATE TABLE IF NOT EXISTS loyalty_accounts (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    balance INTEGER NOT NULL DEFAULT 0,
    lifetime_earned INTEGER NOT NULL DEFAULT 0,
    lifetime_redeemed INTEGER NOT NULL DEFAULT 0,
    tier TEXT NOT NULL DEFAULT 'classic' CHECK(tier IN ('classic','argent','or','platine')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    type TEXT NOT NULL CHECK(type IN ('earned','redeemed','adjustment','refund','expired','bonus')),
    points INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    reference TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_loyalty_tx_user ON loyalty_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_loyalty_tx_order ON loyalty_transactions(order_id);
CREATE INDEX IF NOT EXISTS idx_loyalty_tx_type ON loyalty_transactions(type);

CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name TEXT NOT NULL,
    points_cost INTEGER NOT NULL,
    discount_value INTEGER NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    usage_limit INTEGER,
    per_user_limit INTEGER,
    start_date TEXT,
    end_date TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    title TEXT,
    body TEXT,
    verified_purchase INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected','reported')),
    helpful_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(product_id, user_id, order_id)
);
CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id);
CREATE INDEX IF NOT EXISTS idx_reviews_user ON reviews(user_id);
CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status);

CREATE TABLE IF NOT EXISTS review_helpful (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(review_id, user_id)
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_number TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    subject TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','waiting','resolved','closed')),
    priority TEXT NOT NULL DEFAULT 'normal' CHECK(priority IN ('low','normal','high','urgent')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_support_user ON support_tickets(user_id);
CREATE INDEX IF NOT EXISTS idx_support_status ON support_tickets(status);

CREATE TABLE IF NOT EXISTS support_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_internal INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_support_msg_ticket ON support_messages(ticket_id);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT,
    link TEXT,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notif_read ON notifications(is_read);

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    order_updates INTEGER NOT NULL DEFAULT 1,
    loyalty_updates INTEGER NOT NULL DEFAULT 1,
    promotions INTEGER NOT NULL DEFAULT 1,
    stock_alerts INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS recently_viewed (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    viewed_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(user_id, product_id)
);
CREATE INDEX IF NOT EXISTS idx_recent_user ON recently_viewed(user_id);

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    change_qty INTEGER NOT NULL,
    reason TEXT NOT NULL,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_inv_product ON inventory_movements(product_id);

PRAGMA foreign_keys=ON;
