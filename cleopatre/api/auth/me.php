<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.', 405);
$user = currentUser();
if (!$user) {
  jsonResponse(['success'=>false,'authenticated'=>false,'user'=>null,'csrf_token'=>csrfToken()], 200);
}
$safe = [
  'id' => (int)$user['id'],
  'uuid' => $user['uuid'],
  'first_name' => $user['first_name'],
  'last_name' => $user['last_name'],
  'email' => $user['email'],
  'phone' => $user['phone'],
  'role' => $user['role'],
  'status' => $user['status'],
  'email_verified' => (int)$user['email_verified'],
  'created_at' => $user['created_at'],
];
jsonResponse(['success'=>true,'authenticated'=>true,'user'=>$safe,'csrf_token'=>csrfToken()], 200);
