<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);
// CSRF optionnel sur logout (mais on le vérifie si token présent)
if (!empty($_SESSION['user_id']) && !empty($config['csrf_enabled'])) {
  // ne pas bloquer fort si token absent sur logout GET, mais ici POST on exige ?
  // on tolère absent pour compatibilité, mais si présent on vérifie
  $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
  if ($hdr && !csrfVerify($hdr)) jsonError('Jeton de sécurité invalide.', 403);
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();
jsonSuccess(null, 'Déconnexion réussie.');
