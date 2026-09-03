<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('Méthode non autorisée.',405);
$user=requireAuth(); requireCsrf();
$input=getJsonInput(); if(empty($input)) $input=$_POST;
$id=isset($input['id']) ? (int)$input['id'] : null;
$all=!empty($input['all']);
$pdo=db();
if($all){
  $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
  jsonSuccess(null,'Toutes les notifications marquées comme lues.');
}
if(!$id) jsonError('id requis.',422);
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id,$user['id']]);
jsonSuccess(null,'Marquée comme lue.');
