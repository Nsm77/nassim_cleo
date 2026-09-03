<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
$user=requireAuth(); requireCsrf();
$input=getJsonInput(); if(empty($input)) $input=$_POST;
$productId=trim($input['product_id'] ?? '');
$rating=(int)($input['rating'] ?? 0);
$title=sanitizeString($input['title'] ?? '',120);
$body=sanitizeString($input['body'] ?? '',1000);
$orderId=isset($input['order_id']) ? (int)$input['order_id'] : null;
if(!$productId || $rating<1 || $rating>5) jsonError('Produit et note 1-5 requis.',422);
if(mb_strlen($body)<10) jsonError('Avis trop court (min 10 caractères).',422);
try{
  $pdo=db();
  // check product exists
  $chk=$pdo->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
  $chk->execute([$productId]);
  if(!$chk->fetchColumn()) jsonError('Produit introuvable.',404);
  // verified purchase?
  $verified=0;
  if($orderId){
    $o=$pdo->prepare("SELECT id FROM orders WHERE id=? AND user_id=? AND status IN ('delivered','shipped') LIMIT 1");
    $o->execute([$orderId,$user['id']]);
    if($o->fetchColumn()){
      $oi=$pdo->prepare("SELECT 1 FROM order_items WHERE order_id=? AND product_id=? LIMIT 1");
      $oi->execute([$orderId,$productId]);
      if($oi->fetchColumn()) $verified=1;
    }
  } else {
    $v=$pdo->prepare("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=? AND oi.product_id=? AND o.status='delivered' LIMIT 1");
    $v->execute([$user['id'],$productId]);
    if($v->fetchColumn()) $verified=1;
  }
  $pdo->prepare("INSERT INTO reviews(product_id,user_id,order_id,rating,title,body,verified_purchase,status) VALUES (?,?,?,?,?,?,?, 'pending')")
    ->execute([$productId,$user['id'],$orderId?:null,$rating,$title?:null,$body,$verified]);
  $id=$pdo->lastInsertId();
  adminLog((int)$user['id'],'review_created','review',(string)$id,['product_id'=>$productId,'rating'=>$rating]);
  jsonSuccess(['review_id'=>$id,'verified'=>$verified],'Merci pour votre avis. Il sera publié après modération.',201);
}catch(Throwable $e){
  if(strpos($e->getMessage(),'UNIQUE')!==false) jsonError('Vous avez déjà noté ce produit pour cette commande.',409);
  jsonError('Erreur.',500);
}
