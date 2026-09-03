<?php
require __DIR__ . '/../_bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$user=requireAuth();
try{
  $pdo=db();
  $stmt=$pdo->prepare("SELECT product_id FROM recently_viewed WHERE user_id=? ORDER BY viewed_at DESC LIMIT 12");
  $stmt->execute([$user['id']]);
  $ids=array_column($stmt->fetchAll(),'product_id');
  $products=[];
  foreach($ids as $id){
    $p=$pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
    $p->execute([$id]);
    $row=$p->fetch();
    if($row) $products[]=$row;
  }
  // if not enough, fill with featured
  if(count($products)<6){
    $extra=$pdo->query("SELECT * FROM products WHERE featured=1 LIMIT ".(6-count($products)))->fetchAll();
    $products=array_merge($products,$extra);
  }
  jsonSuccess(['products'=>$products,'ids'=>$ids]);
}catch(Throwable $e){ jsonError('Erreur.',500); }
