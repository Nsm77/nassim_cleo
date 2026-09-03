<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  if (!isSuperAdmin($admin)) jsonError('Accès réservé au Super Admin — action non autorisée pour Admin produits.', 403);
}
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
  $q=trim($_GET['q'] ?? '');
  $where="1=1"; $params=[];
  if($q!==''){ $where.=" AND (slug LIKE ? OR name LIKE ?)"; $like="%$q%"; $params[]=$like;$params[]=$like; }
  $stmt=$pdo->prepare("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_slug=b.slug) as product_count FROM brands b WHERE $where ORDER BY b.name ASC");
  $stmt->execute($params);
  $rows=$stmt->fetchAll();
  foreach($rows as &$r){
    $r['values']=$r['values_json']?json_decode($r['values_json'],true):[];
    $r['story']=$r['story']?json_decode($r['story'],true):[];
  }
  jsonSuccess(['brands'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $slug=trim($input['slug'] ?? '');
  $name=trim($input['name'] ?? '');
  if(!$name) jsonError('Nom requis.',422);
  if(!$slug) $slug=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-',$name));
  $chk=$pdo->prepare("SELECT 1 FROM brands WHERE slug=?");
  $chk->execute([$slug]);
  if($chk->fetchColumn()) jsonError('Slug déjà existant.',409);
  $pdo->prepare("INSERT INTO brands(slug, name, country, est, letter, featured, tint, tagline, story, signature, values_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$slug,$name, trim($input['country']??''), trim($input['est']??''), mb_strtoupper(mb_substr($name,0,1)), isset($input['featured'])?($input['featured']?1:0):0, trim($input['tint']??'#EDEAE0'), trim($input['tagline']??''), json_encode($input['story']??[], JSON_UNESCAPED_UNICODE), trim($input['signature']??''), json_encode($input['values']??[], JSON_UNESCAPED_UNICODE)]);
  adminLog((int)$admin['id'],'brand_create','brand',$slug);
  jsonSuccess(['slug'=>$slug],'Marque créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $slug=trim($input['slug'] ?? '');
  if(!$slug) jsonError('Slug requis.',422);
  $stmt=$pdo->prepare("SELECT * FROM brands WHERE slug=?");
  $stmt->execute([$slug]);
  if(!$stmt->fetch()) jsonError('Marque introuvable.',404);
  $fields=[]; $params=[];
  foreach(['name','country','est','tint','tagline','signature'] as $k){
    if(isset($input[$k])){ $fields[]="$k=?"; $params[]=$input[$k]; }
  }
  if(isset($input['featured'])){ $fields[]="featured=?"; $params[]=$input['featured']?1:0; }
  if(isset($input['story'])){ $fields[]="story=?"; $params[]=json_encode($input['story'], JSON_UNESCAPED_UNICODE); }
  if(isset($input['values'])){ $fields[]="values_json=?"; $params[]=json_encode($input['values'], JSON_UNESCAPED_UNICODE); }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $params[]=$slug;
  $pdo->prepare("UPDATE brands SET ".implode(',',$fields)." WHERE slug=?")->execute($params);
  // if name changed update letter
  if(isset($input['name'])){
    $pdo->prepare("UPDATE brands SET letter=? WHERE slug=?")->execute([mb_strtoupper(mb_substr($input['name'],0,1)),$slug]);
  }
  adminLog((int)$admin['id'],'brand_update','brand',$slug,$input);
  jsonSuccess(null,'Marque mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $slug=trim($_GET['slug'] ?? '');
  if(!$slug){ $j=getJsonInput(); $slug=trim($j['slug'] ?? ''); }
  if(!$slug) jsonError('Slug requis.',422);
  $chk=$pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_slug=?");
  $chk->execute([$slug]);
  if((int)$chk->fetchColumn()>0) jsonError('Marque liée à des produits — réassignez ou désactivez d’abord.',409);
  $pdo->prepare("DELETE FROM brands WHERE slug=?")->execute([$slug]);
  adminLog((int)$admin['id'],'brand_delete','brand',$slug);
  jsonSuccess(null,'Marque supprimée.');
}
jsonError('Méthode non autorisée.',405);
