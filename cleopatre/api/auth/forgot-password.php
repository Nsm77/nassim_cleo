<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);
rateLimitOrFail('forgot', 5, 3600, 'Trop de demandes. Veuillez réessayer plus tard.');

$input = getJsonInput();
if (empty($input)) $input = $_POST;
$email = normalizeEmail(trim($input['email'] ?? ''));
if (!validateEmail($email)) jsonError('Veuillez renseigner une adresse e-mail valide.', 422);

// Toujours répondre succès pour ne pas énumérer les comptes
try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? COLLATE NOCASE LIMIT 1");
  $stmt->execute([$email]);
  $uid = $stmt->fetchColumn();
  if ($uid) {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1h
    $pdo->prepare("INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES (?, ?, ?)")->execute([$uid, $hash, $expires]);
    // Si mail désactivé, logguer le token en dev (ne jamais exposer en prod)
    global $config;
    if (!empty($config['app_debug'])) {
        appLog('info', 'password reset token', ['user_id'=>$uid, 'token'=>$token, 'email'=>maskEmail($email)]);
        // En dev on retourne le token pour faciliter les tests, en prod non
        // On retourne quand même en dev pour tests automatisés
        jsonSuccess(['debug_token'=>$token, 'expires_at'=>$expires], 'Si un compte existe, un e-mail de réinitialisation a été envoyé. Environnement de développement : jeton affiché.');
    } else {
        // Ici on enverrait l'e-mail via provider configuré
        appLog('info', 'password reset requested', ['user_id'=>$uid]);
    }
  }
  jsonSuccess(null, 'Si un compte existe avec cette adresse, vous recevrez un e-mail de réinitialisation.');
} catch (Throwable $e) {
  appLog('error', 'forgot-password error', ['e'=>$e->getMessage()]);
  jsonError('Une erreur est survenue.', 500);
}
