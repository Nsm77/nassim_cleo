<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);

$page = max(1,(int)($_GET['page'] ?? 1));
$per = min(50,max(1,(int)($_GET['per_page'] ?? 10)));
$status = trim($_GET['status'] ?? '');

try {
  $pdo = db();
  $where = "user_id = ?";
  $params = [$user['id']];
  if ($status !== '' && in_array($status, ['pending','confirmed','preparing','shipped','delivered','cancelled'])) {
    $where .= " AND status = ?";
    $params[] = $status;
  }
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $where");
  $cnt->execute($params);
  $total = (int)$cnt->fetchColumn();
  $offset = ($page-1)*$per;
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params, [$per, $offset]));
  $orders = $stmt->fetchAll();
  // enrichir avec nb produits
  foreach($orders as &$o) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
    $c->execute([$o['id']]);
    $o['items_count'] = (int)$c->fetchColumn();
  }
  jsonSuccess(['orders'=>$orders,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
} catch (Throwable $e) {
  jsonError('Erreur.',500);
}
