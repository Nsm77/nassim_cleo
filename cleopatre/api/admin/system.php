<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $checks=[];
  try{
    $pdo->query("SELECT 1");
    $checks['database']='connected';
    $checks['sqlite']='available';
    $required=['users','products','orders','categories','brands','inventory_movements','loyalty_transactions'];
    foreach($required as $t){
      try{ $pdo->query("SELECT 1 FROM $t LIMIT 1"); $checks["table:$t"]='ok'; }catch(Throwable $e){ $checks["table:$t"]='missing'; }
    }
    $checks['php']=PHP_VERSION;
    $checks['pdo_sqlite']=extension_loaded('pdo_sqlite')?'ok':'missing';
    $checks['session']= session_status()===PHP_SESSION_ACTIVE ? 'active' : 'inactive';
    $checks['storage']= is_writable(__DIR__.'/../../database') ? 'writable' : 'read-only';
    $checks['config']= file_exists(__DIR__.'/../../config/config.php') ? 'custom' : 'example';
    $checks['last_health']=date('Y-m-d H:i:s');
    $checks['disk_free']= function_exists('disk_free_space') ? round(disk_free_space(__DIR__.'/../../database')/1024/1024,1).' MB' : 'unknown';
  }catch(Throwable $e){ $checks['database']='unavailable'; $checks['error']=$e->getMessage(); }
  // recent failed logins from logs
  $failed=0;
  try{
    $logFile=dirname(__DIR__,2).'/logs/app.log';
    if(file_exists($logFile)){
      $content=file_get_contents($logFile);
      $failed=substr_count($content,'login failed');
    }
  }catch(Throwable $e){}
  jsonSuccess(['checks'=>$checks,'failed_logins'=>$failed]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $action=trim($input['action'] ?? '');
  if($action==='backup'){
    if($admin['role']!=='super_admin') jsonError('Super Admin requis pour le backup.',403);
    $src=$pdo->query("PRAGMA database_list")->fetchAll();
    // simple file copy
    $dbPath= $GLOBALS['config']['db_path'] ?? __DIR__.'/../../database/cleopatre.sqlite';
    $backupDir=dirname($dbPath).'/backups';
    if(!is_dir($backupDir)) mkdir($backupDir,0755,true);
    $dest=$backupDir.'/backup-'.date('Ymd-His').'.sqlite';
    if(!copy($dbPath,$dest)) jsonError('Échec du backup.',500);
    adminLog((int)$admin['id'],'database_backup','system',null,['file'=>basename($dest)]);
    jsonSuccess(['file'=>basename($dest)],'Backup créé.');
  }
  if($action==='clear_cache'){
    jsonSuccess(null,'Cache effacé (opération simulée, WAL checkpoint).');
  }
  jsonError('Action inconnue.',422);
}
jsonError('Méthode non autorisée.',405);
