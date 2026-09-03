<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);

rateLimitOrFail('login', 10, 900, 'Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.');

$input = getJsonInput();
if (empty($input)) $input = $_POST;
$rawIdentifier = trim($input['email'] ?? $input['username'] ?? '');
$email = normalizeEmail($rawIdentifier);
$pwd = $input['password'] ?? '';
$isNssmLogin = in_array(strtolower($rawIdentifier), ['nssm', 'nssm@cleopatre.tn', 'nssm@cleopatre.local'], true);

if ((!$isNssmLogin && !validateEmail($email)) || $pwd === '') {
  jsonError('Veuillez renseigner votre adresse e-mail et votre mot de passe.', 422);
}

try {
  $pdo = db();
  if ($isNssmLogin) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email IN ('nssm','nssm@cleopatre.tn','nssm@cleopatre.local') COLLATE NOCASE LIMIT 1");
    $stmt->execute();
  } else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? COLLATE NOCASE LIMIT 1");
    $stmt->execute([$email]);
  }
  $user = $stmt->fetch();
  if (!$user) {
    // délai anti-brute force léger
    usleep(200000);
    jsonError('Identifiants invalides. Vérifiez votre adresse e-mail et votre mot de passe.', 401);
  }
  if ($user['status'] !== 'active') {
    $msg = $user['status']==='suspended' ? 'Votre compte a été suspendu temporairement. Veuillez contacter le service client.' : 'Votre compte a été désactivé. Veuillez contacter le service client.';
    jsonError($msg, 403);
  }
  if (!password_verify($pwd, $user['password_hash'])) {
    usleep(200000);
    appLog('warning', 'login failed', ['email'=>$email]);
    jsonError('Identifiants invalides. Vérifiez votre adresse e-mail et votre mot de passe.', 401);
  }
  // rehash si besoin
  if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($pwd, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?")->execute([$newHash, now(), $user['id']]);
  }
  $pdo->prepare("UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?")->execute([now(), now(), $user['id']]);

  session_regenerate_id(true);
  $_SESSION['user_id'] = (int)$user['id'];
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

  $safe = [
    'id' => (int)$user['id'],
    'uuid' => $user['uuid'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'role' => $user['role'],
    'status' => $user['status'],
  ];
  appLog('info', 'login', ['email'=>$email, 'id'=>$user['id']]);
  jsonSuccess(['user'=>$safe, 'csrf_token'=>$_SESSION['csrf_token']], 'Connexion réussie.');

} catch (PDOException $e) {
  appLog('error', 'login error', ['e'=>$e->getMessage()]);
  jsonError('Une erreur est survenue. Veuillez réessayer.', 500);
}
