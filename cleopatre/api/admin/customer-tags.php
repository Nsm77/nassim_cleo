<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
$allowedTags=['VIP','Regular','New','High Value','Potential VIP','Support Required','Fidèle','À risque'];
if($_SERVER['REQUEST_METHOD']==='GET'){
  $uid=(int)($_GET['user_id'] ?? 0);
  if($uid){
    $stmt=$pdo->prepare("SELECT * FROM customer_tags WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    jsonSuccess(['tags'=>$stmt->fetchAll(),'allowed'=>$allowedTags]);
  } else {
    // list all tags with counts for filtering
    $rows=$pdo->query("SELECT tag, COUNT(*) as c FROM customer_tags GROUP BY tag ORDER BY c DESC")->fetchAll();
    jsonSuccess(['tags'=>$rows,'allowed'=>$allowedTags]);
  }
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $uid=(int)($input['user_id'] ?? 0);
  $tag=trim($input['tag'] ?? '');
  if(!$uid || !$tag) jsonError('user_id et tag requis.',422);
  // normalize
  if(!in_array($tag,$allowedTags)){
    // allow custom but sanitize
    $tag=sanitizeString($tag,40);
  }
  try{
    $pdo->prepare("INSERT INTO customer_tags(user_id, tag, created_by) VALUES (?,?,?)")->execute([$uid,$tag,(int)$admin['id']]);
  } catch(Throwable $e){
    jsonError('Tag déjà assigné.',409);
  }
  adminLog((int)$admin['id'],'customer_tag_add','user',(string)$uid,['tag'=>$tag]);
  jsonSuccess(null,'Tag ajouté.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $j=getJsonInput();
  $uid=(int)($_GET['user_id'] ?? $j['user_id'] ?? 0);
  $tag=trim($_GET['tag'] ?? $j['tag'] ?? '');
  $id=(int)($_GET['id'] ?? $j['id'] ?? 0);
  if($id){
    $pdo->prepare("DELETE FROM customer_tags WHERE id=?")->execute([$id]);
  } else if($uid && $tag){
    $pdo->prepare("DELETE FROM customer_tags WHERE user_id=? AND tag=?")->execute([$uid,$tag]);
  } else jsonError('Paramètres manquants.',422);
  adminLog((int)$admin['id'],'customer_tag_remove','user',(string)$uid,['tag'=>$tag]);
  jsonSuccess(null,'Tag retiré.');
}
jsonError('Méthode non autorisée.',405);
