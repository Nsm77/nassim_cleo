<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$user = requireAuth();
try {
  $pdo = db();
  $page = max(1,(int)($_GET['page'] ?? 1));
  $per = min(50,max(1,(int)($_GET['per_page'] ?? 20)));
  $offset = ($page-1)*$per;
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE user_id=?");
  $cnt->execute([$user['id']]);
  $total = (int)$cnt->fetchColumn();
  $stmt = $pdo->prepare("SELECT lt.*, o.order_number FROM loyalty_transactions lt LEFT JOIN orders o ON o.id=lt.order_id WHERE lt.user_id=? ORDER BY lt.created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute([$user['id'],$per,$offset]);
  $rows = $stmt->fetchAll();
  $acc = getLoyaltyAccount((int)$user['id']);
  jsonSuccess(['transactions'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)],'account'=>$acc]);
} catch(Throwable $e){ jsonError('Erreur.',500); }
