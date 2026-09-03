<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $status=trim($_GET['status'] ?? '');
  $page=max(1,(int)($_GET['page']??1));
  $per=min(50,max(1,(int)($_GET['per_page']??15)));
  $where="1=1"; $params=[];
  if($status && in_array($status,['pending','approved','rejected','reported'])){ $where.=" AND r.status=?"; $params[]=$status; }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT r.*, u.first_name, u.last_name, p.name as product_name FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id WHERE $where ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  jsonSuccess(['reviews'=>$stmt->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  $status=trim($input['status'] ?? '');
  if(!$id || !in_array($status,['approved','rejected','pending'])) jsonError('Statut invalide.',422);
  $stmt=$pdo->prepare("SELECT * FROM reviews WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $r=$stmt->fetch();
  if(!$r) jsonError('Avis introuvable.',404);
  $pdo->prepare("UPDATE reviews SET status=?, updated_at=? WHERE id=?")->execute([$status,now(),$id]);
  adminLog((int)$admin['id'],'review_moderate','review',(string)$id,['from'=>$r['status'],'to'=>$status]);
  // notification to user if approved
  if($status==='approved'){
    createNotification((int)$r['user_id'],'review','Votre avis a été publié',"Merci pour votre contribution sur ".$r['product_id'], "pages/product.html?id=".$r['product_id']);
  }
  jsonSuccess(null,'Statut mis à jour.');
}
jsonError('Méthode non autorisée.',405);
