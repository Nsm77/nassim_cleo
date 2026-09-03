<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $rows=$pdo->query("SELECT * FROM loyalty_campaigns ORDER BY created_at DESC")->fetchAll();
  jsonSuccess(['campaigns'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $name=trim($input['name'] ?? '');
  $type=trim($input['type'] ?? 'double_points');
  if(!$name) jsonError('Nom requis.',422);
  $pdo->prepare("INSERT INTO loyalty_campaigns(name, type, multiplier, bonus_points, start_date, end_date, active, conditions_json) VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$name,$type,floatval($input['multiplier']??2),(int)($input['bonus_points']??0), trim($input['start_date']??'')?:null, trim($input['end_date']??'')?:null, isset($input['active'])?($input['active']?1:0):1, json_encode($input['conditions']??[], JSON_UNESCAPED_UNICODE)]);
  $id=$pdo->lastInsertId();
  adminLog((int)$admin['id'],'loyalty_campaign_create','loyalty_campaign',(string)$id,['name'=>$name]);
  jsonSuccess(['id'=>$id],'Campagne créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $fields=[];$params=[];
  foreach(['name','type','multiplier','bonus_points','start_date','end_date','active'] as $k){
    if(isset($input[$k])){ $fields[]="$k=?"; $params[]=$k==='active'?($input[$k]?1:0):$input[$k]; }
  }
  if(isset($input['conditions'])){ $fields[]="conditions_json=?"; $params[]=json_encode($input['conditions'], JSON_UNESCAPED_UNICODE); }
  if(empty($fields)) jsonError('Rien à mettre à jour.',422);
  $params[]=$id;
  $pdo->prepare("UPDATE loyalty_campaigns SET ".implode(',',$fields)." WHERE id=?")->execute($params);
  jsonSuccess(null,'Campagne mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $pdo->prepare("DELETE FROM loyalty_campaigns WHERE id=?")->execute([$id]);
  jsonSuccess(null,'Campagne supprimée.');
}
jsonError('Méthode non autorisée.',405);
