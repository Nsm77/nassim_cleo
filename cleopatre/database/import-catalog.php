<?php
// CLÉOPÂTRE — Import catalogue PHP
require __DIR__ . '/../api/_bootstrap.php';

$catalogJson = __DIR__ . '/catalog.json';
if (!file_exists($catalogJson)) {
    // try to generate via node
    $node = 'node';
    $js = __DIR__ . '/import-catalog.js';
    @exec("$node \"$js\" 2>&1", $out, $code);
    echo implode("\n", $out) . "\n";
}
if (!file_exists($catalogJson)) {
    die("catalog.json introuvable. Exécutez node database/import-catalog.js\n");
}
$data = json_decode(file_get_contents($catalogJson), true);
if (!$data) die("JSON invalide\n");

$pdo = db();
$pdo->beginTransaction();
try {
    // Brands
    $bCount = 0;
    foreach ($data['brands'] as $b) {
        $stmt = $pdo->prepare("INSERT INTO brands(slug, name, country, est, letter, featured, tint, tagline, story, signature, values_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(slug) DO UPDATE SET name=excluded.name, country=excluded.country, est=excluded.est, letter=excluded.letter, featured=excluded.featured, tint=excluded.tint, tagline=excluded.tagline, story=excluded.story, signature=excluded.signature, values_json=excluded.values_json");
        $stmt->execute([
            $b['slug'], $b['name'], $b['country'], $b['est'], $b['letter'], $b['featured'], $b['tint'], $b['tagline'],
            json_encode($b['story'], JSON_UNESCAPED_UNICODE), $b['signature'], json_encode($b['values'], JSON_UNESCAPED_UNICODE)
        ]);
        $bCount++;
    }
    // Categories
    $cCount = 0;
    foreach ($data['categories'] as $c) {
        $stmt = $pdo->prepare("INSERT INTO categories(slug, name, eyebrow, tagline, description, intro, accent, surface, form, keywords)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(slug) DO UPDATE SET name=excluded.name, eyebrow=excluded.eyebrow, tagline=excluded.tagline, description=excluded.description, intro=excluded.intro, accent=excluded.accent, surface=excluded.surface, form=excluded.form, keywords=excluded.keywords");
        $stmt->execute([
            $c['slug'], $c['name'], $c['eyebrow'], $c['tagline'], $c['description'], $c['intro'], $c['accent'], $c['surface'], $c['form'],
            json_encode($c['keywords'], JSON_UNESCAPED_UNICODE)
        ]);
        $cCount++;
    }
    // Products
    $pCount = 0;
    foreach ($data['products'] as $p) {
        // Resolve brand_slug — null if brand not in table (avoid FK fail for Bielenda etc)
        $brandSlug = null;
        if (!empty($p['brand'])) {
            $st = $pdo->prepare("SELECT slug FROM brands WHERE lower(name)=lower(?) LIMIT 1");
            $st->execute([$p['brand']]);
            $brandSlug = $st->fetchColumn() ?: null;
            // if not found, keep null to avoid FK violation (e.g., Bielenda missing from brands.js)
        }
        $stmt = $pdo->prepare("INSERT INTO products(id, brand, brand_slug, name, cat, sub, form, tint, price, old_price, size, concerns, rating, reviews, stock, featured, bestseller, image, image_alt, image_thumb, short, description, ingredients, benefits, usage_text, active, track_stock, stock_quantity)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0)
            ON CONFLICT(id) DO UPDATE SET brand=excluded.brand, brand_slug=excluded.brand_slug, name=excluded.name, cat=excluded.cat, sub=excluded.sub, form=excluded.form, tint=excluded.tint, price=excluded.price, old_price=excluded.old_price, size=excluded.size, concerns=excluded.concerns, rating=excluded.rating, reviews=excluded.reviews, stock=excluded.stock, featured=excluded.featured, bestseller=excluded.bestseller, image=excluded.image, image_alt=excluded.image_alt, image_thumb=excluded.image_thumb, short=excluded.short, description=excluded.description, ingredients=excluded.ingredients, benefits=excluded.benefits, usage_text=excluded.usage_text, updated_at=datetime('now')");
        $stmt->execute([
            $p['id'], $p['brand'], $brandSlug, $p['name'], $p['cat'], $p['sub'], $p['form'], $p['tint'], $p['price'], $p['oldPrice'], $p['size'],
            json_encode($p['concerns'], JSON_UNESCAPED_UNICODE), $p['rating'], $p['reviews'], $p['stock'], $p['featured'], $p['bestseller'],
            $p['image'], $p['imageAlt'], $p['imageThumb'], $p['short'], $p['description'], $p['ingredients'],
            json_encode($p['benefits'], JSON_UNESCAPED_UNICODE), $p['usage']
        ]);
        $pCount++;
    }

    $pdo->commit();
    echo "Import réussi: $bCount marques, $cCount catégories, $pCount produits\n";

    // Vérif
    $cnt = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Total produits en DB: $cnt\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Erreur import: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
