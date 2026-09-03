<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
$slots=['featured','bestsellers','promotions','new_arrivals','selected'];
if($_SERVER['REQUEST_METHOD']==='GET'){
  $data=[];
  foreach($slots as $s){
    $stmt=$pdo->prepare("SELECT product_ids FROM product_collections_cache WHERE slot_key=?");
    $stmt->execute([$s]);
    $ids=$stmt->fetchColumn();
    $data[$s]= $ids ? json_decode($ids,true) : [];
    // enrich with product details if requested
    if(!empty($_GET['enrich'])){
      $prods=[];
      foreach($data[$s] as $pid){
        $p=$pdo->prepare("SELECT id, name, brand, price, image FROM products WHERE id=?");
        $p->execute([$pid]);
        if($r=$p->fetch()) $prods[]=$r;
      }
      $data[$s.'_products']=$prods;
    }
  }
  // also return collections for merchandising
  $cols=$pdo->query("SELECT * FROM collections WHERE active=1 ORDER BY sort_order ASC")->fetchAll();
  $data['collections']=$cols;
  jsonSuccess($data);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $slot=trim($input['slot'] ?? '');
  $ids=$input['product_ids'] ?? [];
  if(!in_array($slot,$slots)) jsonError('Slot invalide.',422);
  if(!is_array($ids)) jsonError('product_ids doit être un tableau.',422);
  // validate ids exist
  $valid=[];
  foreach($ids as $pid){
    $chk=$pdo->prepare("SELECT 1 FROM products WHERE id=?");
    $chk->execute([trim($pid)]);
    if($chk->fetchColumn()) $valid[]=trim($pid);
  }
  $pdo->prepare("INSERT INTO product_collections_cache(slot_key, product_ids, updated_at) VALUES (?,?,?) ON CONFLICT(slot_key) DO UPDATE SET product_ids=excluded.product_ids, updated_at=excluded.updated_at")
    ->execute([$slot, json_encode($valid, JSON_UNESCAPED_UNICODE), now()]);
  // also store as settings for fallback
  $pdo->prepare("INSERT INTO settings(key,value,updated_at) VALUES (?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at")
    ->execute(["merchandising_$slot", json_encode($valid, JSON_UNESCAPED_UNICODE), now()]);
  adminLog((int)$admin['id'],'merchandising_update','merchandising',$slot,['count'=>count($valid)]);
  jsonSuccess(['slot'=>$slot,'product_ids'=>$valid],'Merchandising mis à jour.');
}
jsonError('Méthode non autorisée.',405);
