<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, uuid, first_name, last_name, email, phone, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $data = $stmt->fetch();
    jsonSuccess(['user'=>$data]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $input = getJsonInput();
    if (empty($input)) $input = $_POST;

    $first = sanitizeString($input['first_name'] ?? $user['first_name'], 80);
    $last  = sanitizeString($input['last_name'] ?? $user['last_name'], 80);
    $email = normalizeEmail(trim($input['email'] ?? $user['email']));
    $phone = sanitizeString($input['phone'] ?? $user['phone'] ?? '', 30);

    $errors = [];
    if (mb_strlen($first) < 2) $errors['first_name'] = 'Prénom requis (2 caractères minimum).';
    if (mb_strlen($last) < 2) $errors['last_name'] = 'Nom requis.';
    if (!validateEmail($email)) $errors['email'] = 'Adresse e-mail invalide.';
    if (!validatePhone($phone)) $errors['phone'] = 'Téléphone invalide.';

    if ($errors) jsonError('Veuillez corriger les champs.', 422, $errors);

    // vérifier doublon email
    if (strtolower($email) !== strtolower($user['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? COLLATE NOCASE AND id != ? LIMIT 1");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) jsonError('Cette adresse e-mail est déjà utilisée.', 409, ['email'=>'Cette adresse e-mail est déjà utilisée.']);
    }

    $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, updated_at=? WHERE id=?");
    $stmt->execute([$first, $last, $email, $phone ?: null, now(), $user['id']]);

    $stmt = $pdo->prepare("SELECT id, uuid, first_name, last_name, email, phone, role, status, created_at FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $updated = $stmt->fetch();
    appLog('info','profile update',['user_id'=>$user['id']]);
    jsonSuccess(['user'=>$updated], 'Profil mis à jour.');
}

jsonError('Méthode non autorisée.', 405);
