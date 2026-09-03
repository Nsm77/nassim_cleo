<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.', 405);
$checks = [];
$ok = true;

try {
  $pdo = db();
  $pdo->query("SELECT 1");
  $checks['database'] = 'connected';
  $checks['sqlite'] = 'ok';
  $checks['session'] = session_status()===PHP_SESSION_ACTIVE ? 'ok' : 'inactive';
  $checks['php'] = PHP_VERSION;
  $checks['pdo_sqlite'] = extension_loaded('pdo_sqlite') ? 'ok' : 'missing';
  $checks['storage'] = is_writable(dirname($config['db_path'])) ? 'writable' : 'read-only';
  $checks['config'] = file_exists(__DIR__.'/../../config/config.php') ? 'ok' : 'example';
  // required directories
  foreach(['database','storage','logs'] as $d){
    $p = dirname(__DIR__,2).'/'.$d;
    $checks["dir:$d"] = is_dir($p) ? (is_writable($p) ? 'ok' : 'not-writable') : 'missing';
    if($checks["dir:$d"]==='missing') $ok=false;
  }
  // required tables
  $required = ['users','products','orders','categories','brands','loyalty_accounts','loyalty_transactions','wishlist','promotions','order_items','order_tracking'];
  foreach($required as $t) {
    try { $pdo->query("SELECT 1 FROM $t LIMIT 1"); $checks["table:$t"] = 'ok'; }
    catch(Throwable $e){ $checks["table:$t"]='missing'; $ok=false; }
  }
  $checks['api'] = 'ok';
} catch (Throwable $e) {
  $checks['database'] = 'unavailable';
  $checks['session'] = session_status()===PHP_SESSION_ACTIVE ? 'ok' : 'inactive';
  $checks['error'] = ($config['app_debug'] ?? false) ? $e->getMessage() : 'Database connection failed';
  $ok = false;
}

$status = $ok ? 'ok' : 'error';
$code = $ok ? 200 : 500;
// Expose public-safe settings (sans secrets) pour que le front affiche les bonnes règles métier sans hardcode
$publicConfig = [];
try {
  $publicConfig['free_shipping_threshold'] = (int)getSetting('free_shipping_threshold','99000');
  $publicConfig['default_shipping_cost'] = (int)getSetting('default_shipping_cost','8000');
  $publicConfig['currency'] = getSetting('currency','DT');
  $publicConfig['loyalty_enabled'] = getSetting('loyalty_enabled','1')==='1';
  $publicConfig['loyalty_rate'] = (int)getSetting('loyalty_rate','10');
  $publicConfig['loyalty_reward_threshold'] = (int)getSetting('loyalty_reward_threshold','1000');
  $publicConfig['loyalty_reward_value'] = (int)getSetting('loyalty_reward_value','10000');
  $publicConfig['store_name'] = getSetting('store_name','Cléopâtre');
  $publicConfig['order_prefix'] = getSetting('order_prefix','CLEO');
} catch(Throwable $e) {}
jsonResponse(['success'=>$ok,'status'=>$status,'database'=>$checks['database'] ?? 'unknown','session'=>$checks['session'] ?? 'unknown','checks'=>$checks,'config'=>$publicConfig], $code);
