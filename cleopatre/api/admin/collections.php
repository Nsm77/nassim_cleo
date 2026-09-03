<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  if (!isSuperAdmin($admin)) jsonError('Accès réservé au Super Admin — action non autorisée pour Admin produits.', 403);
}
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  if(isset($_GET['slug'])){
    $slug=trim($_GET['slug']);
    $stmt=$pdo->prepare("SELECT * FROM collections WHERE slug=?");
    $stmt->execute([$slug]);
    $col=$stmt->fetch();
    if(!$col) jsonError('Collection introuvable.',404);
    $stmt=$pdo->prepare("SELECT p.* FROM collection_products cp JOIN products p ON p.id=cp.product_id WHERE cp.collection_id=? ORDER BY cp.sort_order ASC, p.name ASC");
    $stmt->execute([$col['id']]);
    $products=$stmt->fetchAll();
    jsonSuccess(['collection'=>$col,'products'=>$products]);
  }
  $cols=$pdo->query("SELECT c.*, (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id=c.id) as product_count FROM collections c ORDER BY c.sort_order ASC, c.name ASC")->fetchAll();
  jsonSuccess(['collections'=>$cols]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $action=trim($input['action'] ?? 'create');
  if($action==='add_product'){
    $colId=(int)($input['collection_id'] ?? 0);
    $prodId=trim($input['product_id'] ?? '');
    if(!$colId || !$prodId) jsonError('collection_id et product_id requis.',422);
    $pdo->prepare("INSERT OR IGNORE INTO collection_products(collection_id, product_id, sort_order) VALUES (?,?,?)")->execute([$colId,$prodId, (int)($input['sort_order']??0)]);
    jsonSuccess(null,'Produit ajouté.');
  }
  if($action==='remove_product'){
    $colId=(int)($input['collection_id'] ?? 0);
    $prodId=trim($input['product_id'] ?? '');
    $pdo->prepare("DELETE FROM collection_products WHERE collection_id=? AND product_id=?")->execute([$colId,$prodId]);
    jsonSuccess(null,'Produit retiré.');
  }
  // create collection
  $slug=trim($input['slug'] ?? '');
  $name=trim($input['name'] ?? '');
  if(!$name) jsonError('Nom requis.',422);
  if(!$slug) $slug=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-',$name));
  $chk=$pdo->prepare("SELECT 1 FROM collections WHERE slug=?");
  $chk->execute([$slug]);
  if($chk->fetchColumn()) jsonError('Slug déjà utilisé.',409);
  $pdo->prepare("INSERT INTO collections(slug, name, description, active, sort_order) VALUES (?,?,?,?,?)")->execute([$slug,$name, trim($input['description']??''), isset($input['active'])?($input['active']?1:0):1, (int)($input['sort_order']??0)]);
  $id=$pdo->lastInsertId();
  if(!empty($input['product_ids']) && is_array($input['product_ids'])){
    foreach($input['product_ids'] as $pid){
      $pdo->prepare("INSERT OR IGNORE INTO collection_products(collection_id, product_id) VALUES (?,?)")->execute([$id,trim($pid)]);
    }
  }
  adminLog((int)$admin['id'],'collection_create','collection',$slug);
  jsonSuccess(['id'=>$id,'slug'=>$slug],'Collection créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $fields=[];$params=[];
  foreach(['name','description','active','sort_order','slug'] as $k){
    if(isset($input[$k])){ $fields[]="$k=?"; $params[]= $k==='active'?($input[$k]?1:0):$input[$k]; }
  }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $fields[]="updated_at=?"; $params[]=now(); $params[]=$id;
  $pdo->prepare("UPDATE collections SET ".implode(',',$fields)." WHERE id=?")->execute($params);
  adminLog((int)$admin['id'],'collection_update','collection',(string)$id,$input);
  jsonSuccess(null,'Collection mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  $slug=trim($_GET['slug'] ?? '');
  if($id) $pdo->prepare("DELETE FROM collections WHERE id=?")->execute([$id]);
  elseif($slug) $pdo->prepare("DELETE FROM collections WHERE slug=?")->execute([$slug]);
  else jsonError('ID ou slug requis.',422);
  adminLog((int)$admin['id'],'collection_delete','collection',(string)($id?:$slug));
  jsonSuccess(null,'Collection supprimée.');
}
jsonError('Méthode non autorisée.',405);
