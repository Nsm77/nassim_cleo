<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  if (!isSuperAdmin($admin)) jsonError('Accès réservé au Super Admin — action non autorisée pour Admin produits.', 403);
}
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
  $cats=$pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.cat=c.slug) as product_count FROM categories c ORDER BY c.slug ASC")->fetchAll();
  foreach($cats as &$c){
    $c['keywords']= $c['keywords'] ? json_decode($c['keywords'],true) : [];
    // subcategories
    $stmt=$pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.sub=s.name OR p.sub=s.slug) as product_count FROM subcategories s WHERE s.category_slug=? ORDER BY s.name ASC");
    $stmt->execute([$c['slug']]);
    $c['subcategories']=$stmt->fetchAll();
  }
  jsonSuccess(['categories'=>$cats]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $action=trim($input['action'] ?? '');
  if($action==='create_subcategory'){
    $catSlug=trim($input['category_slug'] ?? '');
    $subSlug=trim($input['slug'] ?? '');
    $subName=trim($input['name'] ?? '');
    if(!$catSlug || !$subName) jsonError('Catégorie et nom requis.',422);
    if(!$subSlug) $subSlug=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-',$subName));
    $chk=$pdo->prepare("SELECT 1 FROM categories WHERE slug=?");
    $chk->execute([$catSlug]);
    if(!$chk->fetchColumn()) jsonError('Catégorie parente introuvable.',404);
    $pdo->prepare("INSERT INTO subcategories(category_slug, slug, name) VALUES (?,?,?)")->execute([$catSlug,$subSlug,$subName]);
    adminLog((int)$admin['id'],'subcategory_create','subcategory',$subSlug,['parent'=>$catSlug]);
    jsonSuccess(null,'Sous-catégorie créée.',201);
  }
  // create category
  $slug=trim($input['slug'] ?? '');
  $name=trim($input['name'] ?? '');
  if(!$name) jsonError('Nom requis.',422);
  if(!$slug) $slug=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-',$name));
  $chk=$pdo->prepare("SELECT 1 FROM categories WHERE slug=?");
  $chk->execute([$slug]);
  if($chk->fetchColumn()) jsonError('Slug déjà existant.',409);
  $pdo->prepare("INSERT INTO categories(slug, name, eyebrow, tagline, description, intro, accent, surface, form, keywords) VALUES (?,?,?,?,?,?,?,?,?,?)")
    ->execute([$slug,$name, trim($input['eyebrow']??''), trim($input['tagline']??''), trim($input['description']??''), trim($input['intro']??''), trim($input['accent']??'#8A9481'), trim($input['surface']??'#E9E4D8'), trim($input['form']??''), json_encode($input['keywords']??[], JSON_UNESCAPED_UNICODE)]);
  adminLog((int)$admin['id'],'category_create','category',$slug);
  jsonSuccess(['slug'=>$slug],'Catégorie créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $slug=trim($input['slug'] ?? '');
  if(!$slug) jsonError('Slug requis.',422);
  $stmt=$pdo->prepare("SELECT * FROM categories WHERE slug=?");
  $stmt->execute([$slug]);
  if(!$stmt->fetch()) jsonError('Catégorie introuvable.',404);
  $fields=[]; $params=[];
  foreach(['name','eyebrow','tagline','description','intro','accent','surface','form'] as $k){
    if(isset($input[$k])){ $fields[]="$k=?"; $params[]=$input[$k]; }
  }
  if(isset($input['keywords'])){ $fields[]="keywords=?"; $params[]=json_encode($input['keywords'], JSON_UNESCAPED_UNICODE); }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $params[]=$slug;
  $pdo->prepare("UPDATE categories SET ".implode(',',$fields)." WHERE slug=?")->execute($params);
  adminLog((int)$admin['id'],'category_update','category',$slug,$input);
  jsonSuccess(null,'Catégorie mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $slug=trim($_GET['slug'] ?? '');
  if(!$slug){ $j=getJsonInput(); $slug=trim($j['slug'] ?? ''); }
  if(!$slug) jsonError('Slug requis.',422);
  $isSub=!empty($_GET['is_sub']) || (!empty(getJsonInput()['is_sub']));
  if($isSub){
    // delete subcategory
    $cat=trim($_GET['category_slug'] ?? getJsonInput()['category_slug'] ?? '');
    $chk=$pdo->prepare("SELECT COUNT(*) FROM products WHERE sub=(SELECT name FROM subcategories WHERE slug=? AND category_slug=?)");
    $chk->execute([$slug,$cat]);
    if((int)$chk->fetchColumn()>0) jsonError('Sous-catégorie liée à des produits, suppression bloquée.',409);
    $pdo->prepare("DELETE FROM subcategories WHERE slug=? AND category_slug=?")->execute([$slug,$cat]);
    adminLog((int)$admin['id'],'subcategory_delete','subcategory',$slug);
    jsonSuccess(null,'Sous-catégorie supprimée.');
  } else {
    $chk=$pdo->prepare("SELECT COUNT(*) FROM products WHERE cat=?");
    $chk->execute([$slug]);
    if((int)$chk->fetchColumn()>0) jsonError('Catégorie liée à des produits — désactivez ou déplacez les produits d’abord.',409);
    $pdo->prepare("DELETE FROM subcategories WHERE category_slug=?")->execute([$slug]);
    $pdo->prepare("DELETE FROM categories WHERE slug=?")->execute([$slug]);
    adminLog((int)$admin['id'],'category_delete','category',$slug);
    jsonSuccess(null,'Catégorie supprimée.');
  }
}
jsonError('Méthode non autorisée.',405);
