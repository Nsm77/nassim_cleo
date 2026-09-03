<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.', 405);
$id = trim($_GET['id'] ?? '');
if ($id === '') jsonError('Identifiant produit requis.', 400);
try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
  $stmt->execute([$id]);
  $p = $stmt->fetch();
  if (!$p || !$p['active']) jsonError('Produit introuvable.', 404);
  $p['concerns'] = $p['concerns'] ? json_decode($p['concerns'], true) : [];
  $p['benefits'] = $p['benefits'] ? json_decode($p['benefits'], true) : [];
  $p['price'] = (int)$p['price'];
  $p['old_price'] = $p['old_price'] !== null ? (int)$p['old_price'] : null;
  jsonSuccess(['product'=>$p]);
} catch (Throwable $e) {
  jsonError('Erreur.', 500);
}
