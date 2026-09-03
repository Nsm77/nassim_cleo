<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $rows=$pdo->query("SELECT * FROM loyalty_rewards ORDER BY points_cost ASC")->fetchAll();
  jsonSuccess(['rewards'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $code=strtoupper(trim($input['code'] ?? ''));
  $name=trim($input['name'] ?? '');
  $cost=(int)($input['points_cost'] ?? 0);
  $discount=(int)($input['discount_value'] ?? 0);
  if(!$code || !$name || !$cost || !$discount) jsonError('Code, nom, points et valeur requis.',422);
  $chk=$pdo->prepare("SELECT 1 FROM loyalty_rewards WHERE code=? COLLATE NOCASE");
  $chk->execute([$code]);
  if($chk->fetchColumn()) jsonError('Code déjà existant.',409);
  $pdo->prepare("INSERT INTO loyalty_rewards(code, name, points_cost, discount_value, active, usage_limit, per_user_limit, start_date, end_date) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$code,$name,$cost,$discount, isset($input['active'])?($input['active']?1:0):1, $input['usage_limit']??null, $input['per_user_limit']??null, trim($input['start_date']??'')?:null, trim($input['end_date']??'')?:null]);
  $id=$pdo->lastInsertId();
  adminLog((int)$admin['id'],'reward_create','loyalty_reward',(string)$id);
  jsonSuccess(['id'=>$id],'Récompense créée.',201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $fields=[];$params=[];
  foreach(['code','name','points_cost','discount_value','active','usage_limit','per_user_limit','start_date','end_date'] as $k){
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
  $pdo->prepare("UPDATE loyalty_rewards SET ".implode(',',$fields)." WHERE id=?")->execute($params);
  jsonSuccess(null,'Récompense mise à jour.');
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  $pdo->prepare("DELETE FROM loyalty_rewards WHERE id=?")->execute([$id]);
  jsonSuccess(null,'Récompense supprimée.');
}
jsonError('Méthode non autorisée.',405);
