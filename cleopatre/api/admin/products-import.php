<?php
require __DIR__ . '/../_bootstrap.php';
$admin=requireAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('Méthode non autorisée.',405);
requireCsrf();
if(empty($_FILES['file']) && empty($_POST['csv'])){
  // try json input with rows
  $input=getJsonInput();
  $rows=$input['rows'] ?? null;
  if(!$rows) jsonError('Fichier CSV requis.',422);
} else if(!empty($_POST['csv'])) {
  $rows=null;
  $csv=$_POST['csv'];
} else {
  $file=$_FILES['file']['tmp_name'];
  $csv=file_get_contents($file);
}
if(!isset($rows)){
  $lines=explode("\n", trim($csv));
  if(!count($lines)) jsonError('CSV vide.',422);
  $header=str_getcsv(array_shift($lines));
  $expected=['id','name','brand','price'];
  foreach($expected as $e){ if(!in_array($e,$header)) jsonError("Colonne manquante: $e",422); }
  $rows=[];
  foreach($lines as $l){
    if(trim($l)==='') continue;
    $vals=str_getcsv($l);
    if(count($vals)!==count($header)) continue;
    $rows[]=array_combine($header,$vals);
  }
}
$previewOnly=!empty($_GET['preview']) || !empty($_POST['preview']);
$results=['new'=>[],'update'=>[],'invalid'=>[],'duplicate'=>[]];
$seen=[];
foreach($rows as $idx=>$r){
  $line=$idx+2;
  $id=trim($r['id'] ?? '');
  $name=trim($r['name'] ?? '');
  $brand=trim($r['brand'] ?? '');
  $price=isset($r['price']) ? (int)$r['price'] : null;
  if(!$id || !$name || !$brand || $price===null || $price<0){
    $results['invalid'][]=['line'=>$line,'id'=>$id,'reason'=>'Champs requis manquants ou prix invalide'];
    continue;
  }
  if(isset($seen[$id])){ $results['duplicate'][]=['line'=>$line,'id'=>$id]; continue; }
  $seen[$id]=true;
  $exists=$pdo->prepare("SELECT id FROM products WHERE id=?");
  $exists->execute([$id]);
  if($exists->fetchColumn()){
    $results['update'][]=['line'=>$line,'id'=>$id,'name'=>$name];
  } else {
    $results['new'][]=['line'=>$line,'id'=>$id,'name'=>$name];
  }
}
if($previewOnly){
  jsonSuccess(['preview'=>$results,'counts'=>['new'=>count($results['new']),'update'=>count($results['update']),'invalid'=>count($results['invalid']),'duplicate'=>count($results['duplicate'])]]);
}
$imported=0;
$errors=[];
$pdo->beginTransaction();
try{
  foreach($rows as $r){
    $id=trim($r['id']);
    if(isset($errors[$id])) continue;
    // skip invalid duplicates already flagged
    $found=false;
    foreach($results['invalid'] as $inv){ if($inv['id']===$id) $found=true; }
    foreach($results['duplicate'] as $dup){ /* allow first occurrence */ }
    if($found) continue;
    $exists=$pdo->prepare("SELECT id FROM products WHERE id=?");
    $exists->execute([$id]);
    $isUpdate=(bool)$exists->fetchColumn();
    $data=[
      'id'=>$id,
      'name'=>trim($r['name']),
      'brand'=>trim($r['brand']),
      'brand_slug'=>trim($r['brand_slug'] ?? ''),
      'cat'=>trim($r['cat'] ?? 'visage'),
      'sub'=>trim($r['sub'] ?? ''),
      'form'=>trim($r['form'] ?? ''),
      'tint'=>trim($r['tint'] ?? '#ECE5D8'),
      'price'=>(int)($r['price'] ?? 0),
      'old_price'=>isset($r['old_price']) && $r['old_price']!=='' ? (int)$r['old_price'] : null,
      'size'=>trim($r['size'] ?? ''),
      'stock'=>isset($r['stock'])? (int)$r['stock']:1,
      'featured'=>isset($r['featured'])? (int)$r['featured']:0,
      'bestseller'=>isset($r['bestseller']) && $r['bestseller']!=='' ? (int)$r['bestseller']:null,
      'is_new'=>isset($r['is_new'])? (int)$r['is_new']:0,
      'active'=>isset($r['active'])? (int)$r['active']:1,
      'track_stock'=>isset($r['track_stock'])? (int)$r['track_stock']:0,
      'stock_quantity'=>isset($r['stock_quantity'])? (int)$r['stock_quantity']:0,
    ];
    if($isUpdate){
      $pdo->prepare("UPDATE products SET name=?, brand=?, brand_slug=?, cat=?, sub=?, form=?, tint=?, price=?, old_price=?, size=?, stock=?, featured=?, bestseller=?, is_new=?, active=?, track_stock=?, stock_quantity=?, updated_at=? WHERE id=?")
        ->execute([$data['name'],$data['brand'],$data['brand_slug']?:null,$data['cat'],$data['sub'],$data['form'],$data['tint'],$data['price'],$data['old_price'],$data['size'],$data['stock'],$data['featured'],$data['bestseller'],$data['is_new'],$data['active'],$data['track_stock'],$data['stock_quantity'],now(),$id]);
    } else {
      $pdo->prepare("INSERT INTO products(id, brand, brand_slug, name, cat, sub, form, tint, price, old_price, size, stock, featured, bestseller, is_new, active, track_stock, stock_quantity) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id,$data['brand'],$data['brand_slug']?:null,$data['name'],$data['cat'],$data['sub'],$data['form'],$data['tint'],$data['price'],$data['old_price'],$data['size'],$data['stock'],$data['featured'],$data['bestseller'],$data['is_new'],$data['active'],$data['track_stock'],$data['stock_quantity']]);
    }
    $imported++;
  }
  $pdo->commit();
} catch(Throwable $e){ $pdo->rollBack(); jsonError('Erreur import: '.$e->getMessage(),500); }
adminLog((int)$admin['id'],'products_import','product',null,['imported'=>$imported,'preview'=>$results]);
jsonSuccess(['imported'=>$imported,'preview'=>$results],'Import terminé.');
