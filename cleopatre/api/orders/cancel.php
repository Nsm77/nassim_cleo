<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
requireCsrf();
$user = requireAuth();
$input = getJsonInput();
if (empty($input)) $input = $_POST;
$orderId = (int)($input['order_id'] ?? $input['id'] ?? 0);
if (!$orderId) jsonError('Commande requise.',400);
try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch();
  if (!$order || (int)$order['user_id'] !== (int)$user['id']) jsonError('Commande introuvable.',404);
  if (in_array($order['status'], ['shipped','delivered','cancelled'])) {
    jsonError('Cette commande ne peut plus être annulée (statut : '.$order['status'].').',400);
  }
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE orders SET status='cancelled', updated_at=? WHERE id=?")->execute([now(),$orderId]);
  $pdo->prepare("INSERT INTO order_tracking(order_id,status,note) VALUES (?, 'cancelled', ?)")->execute([$orderId,'Annulée à la demande du client.']);
  // restocker si track_stock
  $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
  $items->execute([$orderId]);
  foreach($items->fetchAll() as $it) {
    $chk = $pdo->prepare("SELECT track_stock FROM products WHERE id=?");
    $chk->execute([$it['product_id']]);
    if ((int)$chk->fetchColumn()===1) {
      $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, stock=1, updated_at=? WHERE id=?")->execute([$it['quantity'], now(), $it['product_id']]);
    }
  }
  $pdo->commit();
  appLog('info','order cancelled',['order_id'=>$orderId,'user_id'=>$user['id']]);
  jsonSuccess(null,'Commande annulée.');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  appLog('error','cancel error',['e'=>$e->getMessage()]);
  jsonError('Erreur.',500);
}
