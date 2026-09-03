<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireAdmin();
// Hybrid search: normal Admin sees only products, Super Admin sees all
$pdo=db();
$isSuper = isSuperAdmin($admin);
if (!$isSuper) {
  // Restrict: admin products only immediately? We'll filter below
}
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$q=trim($_GET['q'] ?? '');
if(mb_strlen($q)<2) jsonError('Requête trop courte (min 2 caractères).',422);
$like="%$q%";
$likeLower="%".mb_strtolower($q)."%";
// PRODUCTS: search id, name, brand, cat, sub, sku if variant
$prodStmt=$pdo->prepare("SELECT id, name, brand, cat, price, stock, active, featured FROM products WHERE id LIKE ? OR name LIKE ? OR brand LIKE ? OR cat LIKE ? OR sub LIKE ? LIMIT 10");
$prodStmt->execute([$like,$like,$like,$like,$like]);
$products=$prodStmt->fetchAll();
// variant sku search fallback
if(!$products){
  try{
    $vStmt=$pdo->prepare("SELECT p.id, p.name, p.brand, p.cat, p.price, p.stock, p.active FROM products p JOIN product_variants v ON v.product_id=p.id WHERE v.sku LIKE ? LIMIT 10");
    $vStmt->execute([$like]);
    $products=$vStmt->fetchAll();
  }catch(Throwable $e){}
}
if ($isSuper) {
  // CUSTOMERS: search email, phone, name
  $custStmt=$pdo->prepare("SELECT id, first_name, last_name, email, phone, status, (SELECT COUNT(*) FROM orders WHERE user_id=users.id) as order_count FROM users WHERE role='customer' AND (email LIKE ? OR phone LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR (first_name || ' ' || last_name) LIKE ?) LIMIT 10");
  $custStmt->execute([$like,$like,$like,$like,$like]);
  $customers=$custStmt->fetchAll();
  // ORDERS: search order_number exact, also email/phone
  $orderStmt=$pdo->prepare("SELECT o.id, o.order_number, o.status, o.total, o.created_at, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.order_number LIKE ? OR u.email LIKE ? OR u.phone LIKE ? ORDER BY o.created_at DESC LIMIT 10");
  $orderStmt->execute([$like,$like,$like]);
  $orders=$orderStmt->fetchAll();
  if(preg_match('/^CLEO/i',$q)){
    $exact=$pdo->prepare("SELECT o.id, o.order_number, o.status, o.total, o.created_at, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.order_number = ? COLLATE NOCASE LIMIT 5");
    $exact->execute([$q]);
    $ex=$exact->fetchAll();
    if($ex) $orders=array_merge($ex,$orders);
  }
} else {
  $customers=[];
  $orders=[];
}
jsonSuccess([
  'query'=>$q,
  'products'=>$products,
  'customers'=>$customers,
  'orders'=>$orders,
  'counts'=>['products'=>count($products),'customers'=>count($customers),'orders'=>count($orders)]
]);
