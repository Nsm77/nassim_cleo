<?php
require __DIR__ . '/../_bootstrap.php';
global $config;
// GET returns safe status (sans db_path sensible en production)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $pdo=db();
  $hasSuper = $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
  $hasProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  $data=[
    'has_super_admin'=> (int)$hasSuper>0,
    'has_admin'=> (int)$hasSuper>0,
    'has_products'=> (int)$hasProducts>0,
    'php'=>PHP_VERSION,
    'sqlite'=> extension_loaded('pdo_sqlite') ? 'ok' : 'missing',
    'setup_enabled'=> (bool)($config['setup_enabled'] ?? false),
  ];
  if ($config['app_debug'] ?? false) $data['db_path']=$config['db_path'];
  jsonSuccess($data);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
// POST sécurisé: vérifie setup_enabled + secret via POST body uniquement (pas de GET)
if (!($config['setup_enabled'] ?? false)) jsonError('Setup désactivé. Activez setup_enabled temporairement dans config.php.',403);
$input=getJsonInput();
if(empty($input)) $input=$_POST;
$secret=trim($input['setup_secret'] ?? $input['secret'] ?? '');
if ($secret === '' || !hash_equals($config['setup_secret'] ?? '', $secret)) {
  jsonError('Secret d’installation invalide.',403);
}
// Vérifier qu'aucun super_admin n'existe (one-time bootstrap)
try {
  $pdo=db();
  $cnt=$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
  if((int)$cnt>0) jsonError('Un Super Admin existe déjà — setup bloqué.',409);
} catch(Throwable $e) {}
$first=sanitizeString($input['first_name'] ?? 'Admin',80);
$last=sanitizeString($input['last_name'] ?? 'Cléopâtre',80);
$email=normalizeEmail(trim($input['email'] ?? ''));
$pwd=$input['password'] ?? '';
if(!validateEmail($email)) jsonError('E-mail invalide.',422);
if(!validatePassword($pwd,$msg)) jsonError($msg,422);
try{
  $pdo=db();
  $chk=$pdo->prepare("SELECT id FROM users WHERE email=? COLLATE NOCASE LIMIT 1");
  $chk->execute([$email]);
  if($chk->fetch()) jsonError('Cet e-mail existe déjà.',409);
  $hash=password_hash($pwd, PASSWORD_DEFAULT);
  $uuid=generateUuid();
  $pdo->prepare("INSERT INTO users(uuid,first_name,last_name,email,password_hash,role,status,created_at,updated_at) VALUES (?,?,?,?,?,'super_admin','active',?,?)")
      ->execute([$uuid,$first,$last,$email,$hash,now(),now()]);
  $id=$pdo->lastInsertId();
  adminLog((int)$id,'super_admin_created','user',(string)$id,['email'=>$email]);
  appLog('info','super_admin created via setup',['email'=>$email]);
  jsonSuccess(['admin_id'=>$id],'Super Admin créé. Désactivez setup_enabled et changez le secret.',201);
}catch(Throwable $e){
  appLog('error','setup error',['e'=>$e->getMessage()]);
  $msg=($config['app_debug'] ?? false) ? $e->getMessage() : 'Erreur interne.';
  jsonError($msg,500);
}
