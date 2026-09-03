<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $rows=$pdo->query("SELECT * FROM promotions ORDER BY created_at DESC")->fetchAll();
  jsonSuccess(['promotions'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $code=strtoupper(trim($input['code'] ?? ''));
  $type=trim($input['type'] ?? '');
  $value=(int)($input['value'] ?? 0);
  $minimum=(int)($input['minimum_order'] ?? 0);
  $maxDisc=isset($input['maximum_discount']) && $input['maximum_discount']!=='' ? (int)$input['maximum_discount'] : null;
  $active=isset($input['active']) ? ($input['active']?1:0) : 1;
  $usageLimit=isset($input['usage_limit']) && $input['usage_limit']!=='' ? (int)$input['usage_limit'] : null;
  $perUser=isset($input['per_user_limit']) && $input['per_user_limit']!=='' ? (int)$input['per_user_limit'] : null;
  $start=trim($input['start_date'] ?? '');
  $end=trim($input['end_date'] ?? '');
  if($code==='' || !in_array($type,['percentage','fixed']) || $value<=0) jsonError('Code, type et valeur requis.',422);
  if($type==='percentage' && $value>100) jsonError('Pourcentage invalide.',422);
  // vérifier doublon
  $chk=$pdo->prepare("SELECT id FROM promotions WHERE code=? COLLATE NOCASE LIMIT 1");
  $chk->execute([$code]);
  if($chk->fetch()) jsonError('Ce code existe déjà.',409);
  $stmt=$pdo->prepare("INSERT INTO promotions(code,type,value,minimum_order,maximum_discount,active,usage_limit,per_user_limit,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
  $stmt->execute([$code,$type,$value,$minimum,$maxDisc,$active,$usageLimit,$perUser,$start?:null,$end?:null]);
  $id=$pdo->lastInsertId();
  adminLog((int)$admin['id'],'promotion_create','promotion',(string)$id,['code'=>$code]);
  $row=$pdo->query("SELECT * FROM promotions WHERE id=$id")->fetch();
  jsonSuccess(['promotion'=>$row],'Promotion créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  if(!$id) jsonError('ID requis.',400);
  $fields=[]; $params=[];
  foreach(['code','type','value','minimum_order','maximum_discount','active','usage_limit','per_user_limit','start_date','end_date'] as $k){
    if(isset($input[$k])){
      $fields[]="$k=?";
      $v=$input[$k];
      if($k==='code') $v=strtoupper(trim($v));
      if($k==='active') $v=$v?1:0;
      $params[]=$v==='' ? null : $v;
    }
  }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $params[]=$id;
  $pdo->prepare("UPDATE promotions SET ".implode(',',$fields).", updated_at=datetime('now') WHERE id=?")->execute($params);
  adminLog((int)$admin['id'],'promotion_update','promotion',(string)$id,$input);
  $row=$pdo->query("SELECT * FROM promotions WHERE id=$id")->fetch();
  jsonSuccess(['promotion'=>$row],'Promotion mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? 0);
  if(!$id) $id=(int)(getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',400);
  $pdo->prepare("DELETE FROM promotions WHERE id=?")->execute([$id]);
  adminLog((int)$admin['id'],'promotion_delete','promotion',(string)$id);
  jsonSuccess(null,'Promotion supprimée.');
}
jsonError('Méthode non autorisée.',405);
