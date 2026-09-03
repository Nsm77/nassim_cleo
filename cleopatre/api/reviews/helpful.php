<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
$user=requireAuth(); requireCsrf();
$input=getJsonInput(); if(empty($input)) $input=$_POST;
$rid=(int)($input['review_id'] ?? 0);
if(!$rid) jsonError('review_id requis.',422);
try{
  $pdo=db();
  $stmt=$pdo->prepare("SELECT id FROM reviews WHERE id=? AND status='approved' LIMIT 1");
  $stmt->execute([$rid]);
  if(!$stmt->fetchColumn()) jsonError('Avis introuvable.',404);
  $chk=$pdo->prepare("SELECT 1 FROM review_helpful WHERE review_id=? AND user_id=? LIMIT 1");
  $chk->execute([$rid,$user['id']]);
  if($chk->fetchColumn()) jsonError('Déjà voté.',409);
  $pdo->prepare("INSERT INTO review_helpful(review_id,user_id) VALUES (?,?)")->execute([$rid,$user['id']]);
  $pdo->prepare("UPDATE reviews SET helpful_count=helpful_count+1 WHERE id=?")->execute([$rid]);
  $cnt=$pdo->query("SELECT helpful_count FROM reviews WHERE id=$rid")->fetchColumn();
  jsonSuccess(['helpful_count'=>(int)$cnt],'Merci pour votre vote.');
}catch(Throwable $e){ jsonError('Erreur.',500); }
