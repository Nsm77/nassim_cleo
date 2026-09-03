<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.', 405);

// Params: q, cat, brand, concern, min, max, sort, page, per_page, featured, bestseller
$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');
$brand = trim($_GET['brand'] ?? ''); // slug
$concern = trim($_GET['concern'] ?? '');
$min = isset($_GET['min']) && $_GET['min'] !== '' ? (int) round(floatval($_GET['min'])*1000) : null;
$max = isset($_GET['max']) && $_GET['max'] !== '' ? (int) round(floatval($_GET['max'])*1000) : null;
$sort = $_GET['sort'] ?? 'featured';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = min(100, max(1, (int)($_GET['per_page'] ?? 24)));
$featured = isset($_GET['featured']) ? (int)$_GET['featured'] : null;
$stock = isset($_GET['stock']) ? $_GET['stock'] : null;

try {
  $pdo = db();
  $where = ["active = 1"];
  $params = [];

  if ($q !== '') {
    $where[] = "(name LIKE ? OR brand LIKE ? OR sub LIKE ? OR cat LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  }
  if ($cat !== '') { $where[] = "cat = ?"; $params[] = $cat; }
  if ($brand !== '') {
    // brand slug -> need to map to brand name? products.brand stores name, brand_slug stores slug
    $where[] = "(brand_slug = ? OR lower(brand) = lower(?))";
    $params[] = $brand; $params[] = $brand;
  }
  if ($concern !== '') {
    $where[] = "concerns LIKE ?";
    $params[] = '%"'.$concern.'"%';
    // fallback simple
    // also check csv
  }
  if ($min !== null) { $where[] = "price >= ?"; $params[] = $min; }
  if ($max !== null) { $where[] = "price <= ?"; $params[] = $max; }
  if ($featured !== null) { $where[] = "featured = ?"; $params[] = $featured ? 1 : 0; }
  if ($stock === '1' || $stock === 1) { $where[] = "stock = 1"; }

  $order = "featured DESC, bestseller IS NULL, bestseller ASC, name ASC";
  switch($sort) {
    case 'price-asc': $order = "price ASC"; break;
    case 'price-desc': $order = "price DESC"; break;
    case 'rating': $order = "rating DESC"; break;
    case 'promo': $order = "(CASE WHEN old_price IS NOT NULL AND old_price>price THEN (1 - price*1.0/old_price) ELSE 0 END) DESC"; break;
    case 'name': $order = "name ASC"; break;
    case 'newest': $order = "created_at DESC"; break;
  }

  $whereSql = implode(' AND ', $where);
  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $whereSql");
  $countStmt->execute($params);
  $total = (int)$countStmt->fetchColumn();

  $offset = ($page-1)*$per;
  $stmt = $pdo->prepare("SELECT * FROM products WHERE $whereSql ORDER BY $order LIMIT ? OFFSET ?");
  $p = array_merge($params, [$per, $offset]);
  // PDO binding limits
  $stmt->execute($p);
  $rows = $stmt->fetchAll();
  // decode json fields
  foreach($rows as &$r) {
    $r['concerns'] = $r['concerns'] ? json_decode($r['concerns'], true) : [];
    $r['benefits'] = $r['benefits'] ? json_decode($r['benefits'], true) : [];
    // price is int millimes
    $r['price'] = (int)$r['price'];
    $r['old_price'] = $r['old_price'] !== null ? (int)$r['old_price'] : null;
  }

  jsonSuccess([
    'products' => $rows,
    'pagination' => [
      'page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=> (int) ceil($total / $per)
    ]
  ]);

} catch (Throwable $e) {
  appLog('error','products list error',['e'=>$e->getMessage()]);
  jsonError('Erreur lors du chargement du catalogue.', 500);
}
