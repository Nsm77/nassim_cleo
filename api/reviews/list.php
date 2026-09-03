<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$productId = trim($_GET['product_id'] ?? '');
if (!$productId) jsonError('product_id requis.',422);
$page=max(1,(int)($_GET['page']??1));
$per=min(50,max(1,(int)($_GET['per_page']??10)));
try{
  $pdo=db();
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM reviews WHERE product_id=? AND status='approved'");
  $cnt->execute([$productId]);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status='approved' ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute([$productId,$per,$offset]);
  $rows=$stmt->fetchAll();
  // aggregate
  $agg=$pdo->prepare("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews WHERE product_id=? AND status='approved'");
  $agg->execute([$productId]);
  $a=$agg->fetch();
  jsonSuccess(['reviews'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)],'aggregate'=>['avg'=>round((float)($a['avg']??0),2),'count'=>(int)($a['cnt']??0)]]);
}catch(Throwable $e){ jsonError('Erreur.',500); }
