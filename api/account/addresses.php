<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    jsonSuccess(['addresses'=>$rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $input = getJsonInput();
    if (empty($input)) $input = $_POST;

    $label = sanitizeString($input['label'] ?? 'Domicile', 40);
    $first = sanitizeString($input['first_name'] ?? '', 80);
    $last  = sanitizeString($input['last_name'] ?? '', 80);
    $phone = sanitizeString($input['phone'] ?? '', 30);
    $addr  = sanitizeString($input['address'] ?? '', 255);
    $city  = sanitizeString($input['city'] ?? '', 80);
    $postal= sanitizeString($input['postal_code'] ?? '', 20);
    $add   = sanitizeString($input['additional_information'] ?? '', 255);
    $isDefault = !empty($input['is_default']) ? 1 : 0;

    $errors=[];
    if (mb_strlen($first)<2) $errors['first_name']='Prénom requis.';
    if (mb_strlen($last)<2) $errors['last_name']='Nom requis.';
    if (mb_strlen($addr)<5) $errors['address']='Adresse requise.';
    if (mb_strlen($city)<2) $errors['city']='Ville requise.';
    if (mb_strlen($postal)<3) $errors['postal_code']='Code postal requis.';
    if (!validatePhone($phone)) $errors['phone']='Téléphone invalide.';
    if ($errors) jsonError('Veuillez corriger les champs.',422,$errors);

    // Si is_default, reset autres
    if ($isDefault) {
        $pdo->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=?")->execute([$user['id']]);
    } else {
        // si première adresse, la mettre par défaut
        $c = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id=?");
        $c->execute([$user['id']]);
        if ((int)$c->fetchColumn()===0) $isDefault=1;
    }

    $stmt = $pdo->prepare("INSERT INTO user_addresses(user_id, label, first_name, last_name, phone, address, city, postal_code, additional_information, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $label, $first, $last, $phone ?: null, $addr, $city, $postal, $add ?: null, $isDefault]);
    $id = $pdo->lastInsertId();
    $row = $pdo->query("SELECT * FROM user_addresses WHERE id=$id")->fetch();
    appLog('info','address create',['user_id'=>$user['id'],'addr_id'=>$id]);
    jsonSuccess(['address'=>$row], 'Adresse ajoutée.', 201);
}

jsonError('Méthode non autorisée.',405);
