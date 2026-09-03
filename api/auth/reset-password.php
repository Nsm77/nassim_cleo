<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);
$input = getJsonInput();
if (empty($input)) $input = $_POST;
$token = trim($input['token'] ?? '');
$new = $input['new_password'] ?? '';
$confirm = $input['confirm_password'] ?? '';
if ($token === '' || $new === '' || $confirm === '') jsonError('Jeton et mots de passe requis.', 422);
if ($new !== $confirm) jsonError('Les mots de passe ne correspondent pas.', 422);
if (!validatePassword($new, $msg)) jsonError($msg, 422);

try {
  $pdo = db();
  $hash = hash('sha256', $token);
  $stmt = $pdo->prepare("SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > datetime('now') LIMIT 1");
  $stmt->execute([$hash]);
  $row = $stmt->fetch();
  if (!$row) jsonError('Jeton invalide ou expiré.', 400);
  $newHash = password_hash($new, PASSWORD_DEFAULT);
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?")->execute([$newHash, now(), $row['user_id']]);
  $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$row['id']]);
  // invalider autres tokens
  $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$row['user_id']]);
  $pdo->commit();
  appLog('info', 'password reset success', ['user_id'=>$row['user_id']]);
  jsonSuccess(null, 'Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  appLog('error', 'reset-password error', ['e'=>$e->getMessage()]);
  jsonError('Une erreur est survenue.', 500);
}
