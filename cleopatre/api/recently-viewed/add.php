<?php
require __DIR__ . '/../_bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('Méthode non autorisée.',405);
$input=getJsonInput(); if(empty($input)) $input=$_POST;
$pid=trim($input['product_id'] ?? '');
if(!$pid) jsonError('product_id requis.',422);
$user=currentUser();
if($user){
  try{
    $pdo=db();
    $pdo->prepare("INSERT INTO recently_viewed(user_id, product_id, viewed_at) VALUES (?,?, datetime('now')) ON CONFLICT(user_id, product_id) DO UPDATE SET viewed_at=datetime('now')")
      ->execute([$user['id'],$pid]);
  }catch(Throwable $e){}
}
jsonSuccess(null,'ok');
