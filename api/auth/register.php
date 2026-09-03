<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.', 405);

// Rate limiting
rateLimitOrFail('register', 5, 3600, 'Trop de tentatives d’inscription. Veuillez réessayer dans une heure.');

$input = getJsonInput();
if (empty($input)) $input = $_POST;

$first = sanitizeString($input['first_name'] ?? '', 80);
$last  = sanitizeString($input['last_name'] ?? '', 80);
$email = normalizeEmail(trim($input['email'] ?? ''));
$phone = sanitizeString($input['phone'] ?? '', 30);
$pwd   = $input['password'] ?? '';
$pwd2  = $input['password_confirm'] ?? $input['confirm_password'] ?? '';

$errors = [];
if (mb_strlen($first) < 2) $errors['first_name'] = 'Veuillez renseigner votre prénom (2 caractères minimum).';
if (mb_strlen($last) < 2) $errors['last_name'] = 'Veuillez renseigner votre nom (2 caractères minimum).';
if (!validateEmail($email)) $errors['email'] = 'Veuillez renseigner une adresse e-mail valide.';
if (!validatePhone($phone)) $errors['phone'] = 'Numéro de téléphone invalide.';
if (!validatePassword($pwd, $msg)) $errors['password'] = $msg ?? 'Mot de passe invalide.';
if ($pwd !== $pwd2) $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';

if ($errors) jsonError('Veuillez corriger les champs indiqués.', 422, $errors);

try {
  $pdo = db();
  // vérifier doublon
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? COLLATE NOCASE LIMIT 1");
  $stmt->execute([$email]);
  if ($stmt->fetch()) jsonError('Cette adresse e-mail est déjà utilisée.', 409, ['email'=>'Cette adresse e-mail est déjà utilisée.']);

  $hash = password_hash($pwd, PASSWORD_DEFAULT);
  $uuid = generateUuid();
  $now = now();
  $stmt = $pdo->prepare("INSERT INTO users(uuid, first_name, last_name, email, phone, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'customer', 'active', ?, ?)");
  $stmt->execute([$uuid, $first, $last, $email, $phone ?: null, $hash, $now, $now]);
  $id = (int)$pdo->lastInsertId();

  // auto login
  session_regenerate_id(true);
  $_SESSION['user_id'] = $id;
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

  $user = $pdo->query("SELECT id, uuid, first_name, last_name, email, phone, role, status, created_at FROM users WHERE id = $id")->fetch();
  appLog('info', 'register', ['email'=>$email, 'id'=>$id]);
  jsonSuccess([
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token']
  ], 'Compte créé avec succès.', 201);

} catch (PDOException $e) {
  if (str_contains($e->getMessage(), 'UNIQUE') || $e->getCode() === '23000') {
    jsonError('Cette adresse e-mail est déjà utilisée.', 409);
  }
  appLog('error', 'register failed', ['e'=>$e->getMessage()]);
  jsonError('Une erreur est survenue. Veuillez réessayer.', 500);
}
