<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $uid=(int)($_GET['user_id'] ?? 0);
  if(!$uid) jsonError('user_id requis.',422);
  $stmt=$pdo->prepare("SELECT n.*, u.first_name as admin_first, u.last_name as admin_last FROM customer_notes n LEFT JOIN users u ON u.id=n.admin_id WHERE n.user_id=? ORDER BY n.created_at DESC");
  $stmt->execute([$uid]);
  jsonSuccess(['notes'=>$stmt->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $uid=(int)($input['user_id'] ?? 0);
  $note=trim($input['note'] ?? '');
  if(!$uid || !$note) jsonError('user_id et note requis.',422);
  if(mb_strlen($note)>2000) jsonError('Note trop longue (2000 max).',422);
  $pdo->prepare("INSERT INTO customer_notes(user_id, admin_id, note) VALUES (?,?,?)")->execute([$uid,(int)$admin['id'],$note]);
  adminLog((int)$admin['id'],'customer_note_add','user',(string)$uid,['note'=>mb_substr($note,0,80)]);
  jsonSuccess(null,'Note ajoutée.',201);
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $pdo->prepare("DELETE FROM customer_notes WHERE id=?")->execute([$id]);
  jsonSuccess(null,'Note supprimée.');
}
jsonError('Méthode non autorisée.',405);
