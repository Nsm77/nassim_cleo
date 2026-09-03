<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $status=trim($_GET['status'] ?? '');
  $page=max(1,(int)($_GET['page'] ?? 1));
  $per=min(50,max(1,(int)($_GET['per_page'] ?? 15)));
  $where="1=1"; $params=[];
  if($status && in_array($status,['new','read','resolved'])){ $where.=" AND status=?"; $params[]=$status; }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  jsonSuccess(['messages'=>$stmt->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  $status=trim($input['status'] ?? '');
  if(!$id || !in_array($status,['new','read','resolved'])) jsonError('Statut invalide.',422);
  $pdo->prepare("UPDATE contact_messages SET status=? WHERE id=?")->execute([$status,$id]);
  adminLog((int)$admin['id'],'contact_status','contact_message',(string)$id,['status'=>$status]);
  jsonSuccess(null,'Statut mis à jour.');
}
jsonError('Méthode non autorisée.',405);
