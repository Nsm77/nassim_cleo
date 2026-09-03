<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)(getJsonInput()['id'] ?? 0);
if (!$id) jsonError('Identifiant d’adresse requis.', 400);

// vérifier propriété
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id=? AND user_id=?");
$stmt->execute([$id, $user['id']]);
$addr = $stmt->fetch();
if (!$addr) jsonError('Adresse introuvable.', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess(['address'=>$addr]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    requireCsrf();
    $input = getJsonInput();
    if (empty($input)) $input = $_POST;
    $label = sanitizeString($input['label'] ?? $addr['label'], 40);
    $first = sanitizeString($input['first_name'] ?? $addr['first_name'], 80);
    $last  = sanitizeString($input['last_name'] ?? $addr['last_name'], 80);
    $phone = sanitizeString($input['phone'] ?? $addr['phone'] ?? '', 30);
    $a     = sanitizeString($input['address'] ?? $addr['address'], 255);
    $city  = sanitizeString($input['city'] ?? $addr['city'], 80);
    $postal= sanitizeString($input['postal_code'] ?? $addr['postal_code'], 20);
    $add   = sanitizeString($input['additional_information'] ?? $addr['additional_information'] ?? '', 255);
    $isDefault = isset($input['is_default']) ? (!empty($input['is_default'])?1:0) : (int)$addr['is_default'];

    $errors=[];
    if (mb_strlen($first)<2) $errors['first_name']='Prénom requis.';
    if (mb_strlen($last)<2) $errors['last_name']='Nom requis.';
    if (mb_strlen($a)<5) $errors['address']='Adresse requise.';
    if (mb_strlen($city)<2) $errors['city']='Ville requise.';
    if (mb_strlen($postal)<3) $errors['postal_code']='Code postal requis.';
    if (!validatePhone($phone)) $errors['phone']='Téléphone invalide.';
    if ($errors) jsonError('Veuillez corriger.',422,$errors);

    if ($isDefault && !$addr['is_default']) {
        $pdo->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=?")->execute([$user['id']]);
    }

    $stmt = $pdo->prepare("UPDATE user_addresses SET label=?, first_name=?, last_name=?, phone=?, address=?, city=?, postal_code=?, additional_information=?, is_default=?, updated_at=? WHERE id=?");
    $stmt->execute([$label,$first,$last,$phone?:null,$a,$city,$postal,$add?:null,$isDefault,now(),$id]);
    $row = $pdo->query("SELECT * FROM user_addresses WHERE id=$id")->fetch();
    jsonSuccess(['address'=>$row],'Adresse mise à jour.');
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    requireCsrf();
    $pdo->prepare("DELETE FROM user_addresses WHERE id=?")->execute([$id]);
    // si c'était défaut, mettre une autre par défaut
    $stmt = $pdo->prepare("SELECT id FROM user_addresses WHERE user_id=? AND is_default=1 LIMIT 1");
    $stmt->execute([$user['id']]);
    if (!$stmt->fetch()) {
        $first = $pdo->prepare("SELECT id FROM user_addresses WHERE user_id=? ORDER BY created_at ASC LIMIT 1");
        $first->execute([$user['id']]);
        $fid = $first->fetchColumn();
        if ($fid) $pdo->prepare("UPDATE user_addresses SET is_default=1 WHERE id=?")->execute([$fid]);
    }
    jsonSuccess(null,'Adresse supprimée.');
}
jsonError('Méthode non autorisée.',405);
