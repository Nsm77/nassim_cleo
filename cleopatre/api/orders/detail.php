<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$id = $_GET['id'] ?? $_GET['order_id'] ?? null;
$number = $_GET['order_number'] ?? null;
if (!$id && !$number) jsonError('Identifiant commande requis.',400);
try {
  $pdo = db();
  if ($number) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=? LIMIT 1");
    $stmt->execute([$number]);
  } else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $stmt->execute([(int)$id]);
  }
  $order = $stmt->fetch();
  if (!$order) jsonError('Commande introuvable.',404);
  // vérifier propriété sauf admin
  if ((int)$order['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
    // ne pas révéler existence
    jsonError('Commande introuvable.',404);
  }
  $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
  $items->execute([$order['id']]);
  $tracking = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id=? ORDER BY created_at ASC");
  $tracking->execute([$order['id']]);
  $ship = $order['shipping_address_json'] ? json_decode($order['shipping_address_json'], true) : null;
  jsonSuccess(['order'=>$order,'items'=>$items->fetchAll(),'tracking'=>$tracking->fetchAll(),'shipping_address'=>$ship]);
} catch (Throwable $e) {
  if ($e->getMessage()==='Commande introuvable.') throw $e;
  appLog('error','order detail error',['e'=>$e->getMessage()]);
  jsonError('Erreur.',500);
}
