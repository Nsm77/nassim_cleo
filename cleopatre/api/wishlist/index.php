<?php
require __DIR__ . '/../_bootstrap.php';
$user = requireAuth();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT w.product_id, w.created_at, p.name, p.brand, p.price, p.image, p.stock FROM wishlist w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.created_at DESC");
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    jsonSuccess(['wishlist'=>$rows, 'count'=>count($rows)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $input = getJsonInput();
    if (empty($input)) $input = $_POST;
    $pid = trim($input['product_id'] ?? $input['id'] ?? '');
    $action = $input['action'] ?? 'toggle'; // add, remove, toggle, sync
    if ($action === 'sync' && isset($input['product_ids']) && is_array($input['product_ids'])) {
        // merge guest wishlist
        $ids = array_unique(array_map('trim', $input['product_ids']));
        $ids = array_filter($ids, fn($v)=>$v!=='');
        $added=0;
        foreach($ids as $pid2) {
            // vérifier produit existe
            $chk = $pdo->prepare("SELECT id FROM products WHERE id=? AND active=1 LIMIT 1");
            $chk->execute([$pid2]);
            if (!$chk->fetch()) continue;
            try {
                $pdo->prepare("INSERT OR IGNORE INTO wishlist(user_id, product_id) VALUES (?, ?)")->execute([$user['id'],$pid2]);
                $added++;
            } catch(Throwable $e){}
        }
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id=?");
        $stmt->execute([$user['id']]);
        $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonSuccess(['wishlist'=>$all,'merged'=>$added], 'Liste synchronisée.');
    }

    if ($pid === '') jsonError('Identifiant produit requis.',400);
    // vérifier produit
    $chk = $pdo->prepare("SELECT id FROM products WHERE id=? AND active=1 LIMIT 1");
    $chk->execute([$pid]);
    if (!$chk->fetch()) jsonError('Produit introuvable.',404);

    if ($action === 'remove') {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$user['id'],$pid]);
        jsonSuccess(['product_id'=>$pid,'in_wishlist'=>false],'Retiré des favoris.');
    }
    if ($action === 'add') {
        $pdo->prepare("INSERT OR IGNORE INTO wishlist(user_id, product_id) VALUES (?, ?)")->execute([$user['id'],$pid]);
        jsonSuccess(['product_id'=>$pid,'in_wishlist'=>true],'Ajouté aux favoris.');
    }
    // toggle
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=? LIMIT 1");
    $stmt->execute([$user['id'],$pid]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$user['id'],$pid]);
        jsonSuccess(['product_id'=>$pid,'in_wishlist'=>false],'Retiré des favoris.');
    } else {
        $pdo->prepare("INSERT INTO wishlist(user_id, product_id) VALUES (?, ?)")->execute([$user['id'],$pid]);
        jsonSuccess(['product_id'=>$pid,'in_wishlist'=>true],'Ajouté aux favoris.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    requireCsrf();
    $pid = trim($_GET['product_id'] ?? $_GET['id'] ?? '');
    if ($pid==='') $pid = trim(getJsonInput()['product_id'] ?? '');
    if ($pid==='') jsonError('Identifiant requis.',400);
    $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$user['id'],$pid]);
    jsonSuccess(null,'Retiré.');
}

jsonError('Méthode non autorisée.',405);
