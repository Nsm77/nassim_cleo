<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);
requireCsrf();
$user = requireAuth();

$input = getJsonInput();
if (empty($input)) $input = $_POST;
$current = $input['current_password'] ?? '';
$new = $input['new_password'] ?? '';
$confirm = $input['confirm_password'] ?? $input['new_password_confirm'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
  jsonError('Veuillez renseigner tous les champs.', 422);
}
if ($new !== $confirm) jsonError('Les mots de passe ne correspondent pas.', 422, ['confirm_password'=>'Les mots de passe ne correspondent pas.']);
if (!validatePassword($new, $msg)) jsonError($msg, 422, ['new_password'=>$msg]);

try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
  $stmt->execute([$user['id']]);
  $hash = $stmt->fetchColumn();
  if (!password_verify($current, $hash)) {
    jsonError('Le mot de passe actuel est incorrect.', 401, ['current_password'=>'Le mot de passe actuel est incorrect.']);
  }
  if (password_verify($new, $hash)) {
    jsonError('Le nouveau mot de passe doit être différent de l’actuel.', 422);
  }
  $newHash = password_hash($new, PASSWORD_DEFAULT);
  $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?")->execute([$newHash, now(), $user['id']]);
  // régénère session
  session_regenerate_id(true);
  appLog('info', 'password change', ['user_id'=>$user['id']]);
  adminLog((int)$user['id'], 'password_change', 'user', (string)$user['id']);
  jsonSuccess(null, 'Mot de passe mis à jour avec succès.');
} catch (PDOException $e) {
  appLog('error', 'change-password error', ['e'=>$e->getMessage()]);
  jsonError('Une erreur est survenue.', 500);
}
