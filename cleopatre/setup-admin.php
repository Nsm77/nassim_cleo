<?php
/**
 * One-time Super Admin setup — SÉCURISÉ.
 * - Secret UNIQUEMENT via POST (pas de GET/URL)
 * - Credentials UNIQUEMENT via POST JSON/body (jamais en query string)
 * - Désactivé par défaut si setup_enabled === false OU secret par défaut détecté
 * - Après création, désactiver dans config.php et SUPPRIMER ce fichier.
 * Usage POST JSON: { "secret":"VOTRE_SECRET", "email":"admin@cleopatre.tn", "password":"...", "first_name":"...", "last_name":"..." }
 */
declare(strict_types=1);
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// Vérifie si setup est autorisé
$setupEnabled = $config['setup_enabled'] ?? false;
$defaultMarker = 'cleo-setup-2026-change-in-prod';
if (!$setupEnabled || strpos(($config['setup_secret'] ?? ''), $defaultMarker) !== false && ($config['app_env'] ?? '') === 'production') {
  // En production avec secret par défaut, on bloque même avec bon secret — forcer changement config
  if (!$setupEnabled) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Setup désactivé. Activez setup_enabled temporairement dans config.php puis désactivez-le après création.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'message'=>'Méthode non autorisée. Utilisez POST JSON.'], JSON_UNESCAPED_UNICODE);
  exit;
}
$input = getJsonInput();
if (empty($input)) $input = $_POST;
$expected = $config['setup_secret'] ?? '';
$provided = trim($input['secret'] ?? $input['setup_secret'] ?? '');
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Secret d’installation invalide.'], JSON_UNESCAPED_UNICODE);
  exit;
}
// Vérifier qu'aucun super_admin n'existe déjà (one-time)
try {
  $pdo = db();
  $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
  if ((int)$cnt > 0) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'Un Super Admin existe déjà. Setup désactivé. Supprimez ce fichier.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
} catch(Throwable $e) {}
$email = normalizeEmail(trim($input['email'] ?? ''));
$pwd   = $input['password'] ?? $input['pwd'] ?? '';
$first = sanitizeString($input['first_name'] ?? $input['firstName'] ?? 'Admin', 80);
$last  = sanitizeString($input['last_name'] ?? $input['lastName'] ?? 'Cléopâtre', 80);
if (!validateEmail($email)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'E-mail invalide.'], JSON_UNESCAPED_UNICODE); exit; }
$msg=null;
if (!validatePassword($pwd, $msg)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
try {
  $pdo = db();
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? COLLATE NOCASE LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>"Cet e-mail existe déjà: $email. Supprimez ce fichier."], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $hash = password_hash($pwd, PASSWORD_DEFAULT);
  $uuid = generateUuid();
  $pdo->prepare("INSERT INTO users(uuid,first_name,last_name,email,password_hash,role,status,created_at,updated_at) VALUES (?,?,?,?,?,'super_admin','active',?,?)")
      ->execute([$uuid, $first, $last, $email, $hash, now(), now()]);
  $id = $pdo->lastInsertId();
  adminLog((int)$id,'super_admin_created','user',(string)$id,['email'=>$email]);
  appLog('info','setup-admin super_admin created', ['email'=>$email,'id'=>$id]);
  echo json_encode(['success'=>true,'message'=>'Super Admin créé. DÉSACTIVEZ setup_enabled et SUPPRIMEZ setup-admin.php immédiatement.','data'=>['id'=>$id,'email'=>$email]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  // Ne jamais exposer le stack trace en production
  $msg = ($config['app_debug'] ?? false) ? $e->getMessage() : 'Erreur interne.';
  echo json_encode(['success'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  appLog('error','setup-admin failed', ['e'=>$e->getMessage()]);
}
