<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $oid=(int)($_GET['order_id'] ?? 0);
  if(!$oid) jsonError('order_id requis.',422);
  $stmt=$pdo->prepare("SELECT n.*, u.first_name, u.last_name FROM order_internal_notes n LEFT JOIN users u ON u.id=n.admin_id WHERE n.order_id=? ORDER BY n.created_at DESC");
  $stmt->execute([$oid]);
  jsonSuccess(['notes'=>$stmt->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $oid=(int)($input['order_id'] ?? 0);
  $note=trim($input['note'] ?? '');
  if(!$oid || !$note) jsonError('order_id et note requis.',422);
  $pdo->prepare("INSERT INTO order_internal_notes(order_id, admin_id, note) VALUES (?,?,?)")->execute([$oid,(int)$admin['id'],$note]);
  $pdo->prepare("INSERT INTO order_tracking(order_id, status, note, created_by) VALUES (?,?,?,?)")->execute([$oid, 'note', '[Note interne] '.$note, (int)$admin['id']]);
  adminLog((int)$admin['id'],'order_note_add','order',(string)$oid);
  jsonSuccess(null,'Note ajoutée.',201);
}
jsonError('Méthode non autorisée.',405);
