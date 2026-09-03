<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->query("SELECT fs.*, (SELECT COUNT(*) FROM flash_sale_products fsp WHERE fsp.flash_sale_id=fs.id) as product_count FROM flash_sales fs ORDER BY fs.start_date DESC");
  $rows=$stmt->fetchAll();
  foreach($rows as &$r){
    $now=date('Y-m-d H:i:s');
    if($r['active']==0) $r['status']='DÉSACTIVÉE';
    elseif($now < $r['start_date']) $r['status']='PROGRAMMÉE';
    elseif($now > $r['end_date']) $r['status']='EXPIRÉE';
    else $r['status']='ACTIVE';
    $p=$pdo->prepare("SELECT p.id, p.name, p.price FROM flash_sale_products fsp JOIN products p ON p.id=fsp.product_id WHERE fsp.flash_sale_id=?");
    $p->execute([$r['id']]);
    $r['products']=$p->fetchAll();
  }
  jsonSuccess(['flash_sales'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $name=trim($input['name'] ?? '');
  $dtype=trim($input['discount_type'] ?? 'percentage');
  $dval=(int)($input['discount_value'] ?? 0);
  $start=trim($input['start_date'] ?? '');
  $end=trim($input['end_date'] ?? '');
  $productIds=$input['product_ids'] ?? [];
  if(!$name || !$dval || !$start || !$end) jsonError('Nom, valeur, dates requis.',422);
  if(!in_array($dtype,['percentage','fixed'])) jsonError('Type invalide.',422);
  if($dtype==='percentage' && $dval>90) jsonError('Remise trop élevée.',422);
  $pdo->prepare("INSERT INTO flash_sales(name, discount_type, discount_value, start_date, end_date, active, created_by) VALUES (?,?,?,?,?,?,?)")
    ->execute([$name,$dtype,$dval,$start,$end, isset($input['active'])?($input['active']?1:0):1, (int)$admin['id']]);
  $id=$pdo->lastInsertId();
  if(is_array($productIds)){
    foreach($productIds as $pid){
      $pdo->prepare("INSERT OR IGNORE INTO flash_sale_products(flash_sale_id, product_id) VALUES (?,?)")->execute([$id,trim($pid)]);
    }
  }
  // apply pricing immediately if active now
  $now=date('Y-m-d H:i:s');
  if($now >= $start && $now <= $end){
    foreach($productIds as $pid){
      $p=$pdo->prepare("SELECT price, old_price FROM products WHERE id=?"); $p->execute([trim($pid)]); $prod=$p->fetch();
      if(!$prod) continue;
      $newPrice= $dtype==='percentage' ? (int)round($prod['price']*(1 - $dval/100)) : max(0,$prod['price']-$dval);
      $old=$prod['old_price'] ?: $prod['price'];
      $pdo->prepare("UPDATE products SET old_price=?, price=?, promo_active=1, promo_discount_type=?, promo_discount_value=?, promo_start=?, promo_end=?, updated_at=? WHERE id=?")->execute([$old,$newPrice,$dtype,$dval,$start,$end,now(),trim($pid)]);
    }
  }
  adminLog((int)$admin['id'],'flash_sale_create','flash_sale',(string)$id,['name'=>$name]);
  jsonSuccess(['id'=>$id],'Flash sale créé.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $fields=[];$params=[];
  foreach(['name','discount_type','discount_value','start_date','end_date','active'] as $k){
    if(isset($input[$k])){ $fields[]="$k=?"; $params[]=$k==='active'?($input[$k]?1:0):$input[$k]; }
  }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $params[]=$id;
  $pdo->prepare("UPDATE flash_sales SET ".implode(',',$fields)." WHERE id=?")->execute($params);
  jsonSuccess(null,'Flash sale mis à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  // restore prices for products in this flash sale if they were modified
  $stmt=$pdo->prepare("SELECT product_id FROM flash_sale_products WHERE flash_sale_id=?");
  $stmt->execute([$id]);
  foreach($stmt->fetchAll() as $r){
    $p=$pdo->prepare("SELECT old_price, price FROM products WHERE id=?"); $p->execute([$r['product_id']]); $prod=$p->fetch();
    if($prod && $prod['old_price']){
      $pdo->prepare("UPDATE products SET price=old_price, old_price=NULL, promo_active=0 WHERE id=?")->execute([$r['product_id']]);
    }
  }
  $pdo->prepare("DELETE FROM flash_sales WHERE id=?")->execute([$id]);
  jsonSuccess(null,'Flash sale supprimé.');
}
jsonError('Méthode non autorisée.',405);
