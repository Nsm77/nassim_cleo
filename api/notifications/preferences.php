<?php
require __DIR__ . '/../_bootstrap.php';
$user=requireAuth();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->prepare("SELECT * FROM notification_preferences WHERE user_id=? LIMIT 1");
  $stmt->execute([$user['id']]);
  $prefs=$stmt->fetch();
  if(!$prefs){
    $pdo->prepare("INSERT OR IGNORE INTO notification_preferences(user_id) VALUES (?)")->execute([$user['id']]);
    $stmt->execute([$user['id']]);
    $prefs=$stmt->fetch();
  }
  jsonSuccess(['preferences'=>$prefs]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $fields=['order_updates','loyalty_updates','promotions','stock_alerts'];
  $vals=[];
  foreach($fields as $f){ $vals[$f]= !empty($input[$f]) ? 1 : 0; }
  $pdo->prepare("INSERT INTO notification_preferences(user_id, order_updates, loyalty_updates, promotions, stock_alerts) VALUES (?,?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET order_updates=excluded.order_updates, loyalty_updates=excluded.loyalty_updates, promotions=excluded.promotions, stock_alerts=excluded.stock_alerts, updated_at=datetime('now')")
    ->execute([$user['id'],$vals['order_updates'],$vals['loyalty_updates'],$vals['promotions'],$vals['stock_alerts']]);
  jsonSuccess(null,'Préférences mises à jour.');
}
jsonError('Méthode non autorisée.',405);
