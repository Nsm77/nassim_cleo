<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $rows=$pdo->query("SELECT key, value FROM settings ORDER BY key ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
  jsonSuccess(['settings'=>$rows]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
  requireCsrf();
  $input=getJsonInput();
  if(empty($input)) $input=$_POST;
  $allowed=['store_name','store_phone','store_email','free_shipping_threshold','default_shipping_cost','currency','order_prefix'];
  $updated=[];
  foreach($allowed as $k){
    if(isset($input[$k])){
      $v=trim((string)$input[$k]);
      if($k==='free_shipping_threshold' || $k==='default_shipping_cost'){
        if(!is_numeric($v) || (int)$v <0) jsonError("Valeur invalide pour $k",422);
        $v=(string)(int)$v;
      }
      $pdo->prepare("INSERT INTO settings(key,value,updated_at) VALUES (?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at")
          ->execute([$k,$v,now()]);
      $updated[$k]=$v;
    }
  }
  if(empty($updated)) jsonError('Aucun paramètre fourni.',422);
  adminLog((int)$admin['id'],'settings_update','settings',null,$updated);
  jsonSuccess(['settings'=>$updated],'Paramètres mis à jour.');
}
jsonError('Méthode non autorisée.',405);
