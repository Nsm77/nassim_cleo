<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$user = requireAuth();
try {
  $pdo = db();
  $acc = getLoyaltyAccount((int)$user['id']);
  $rewards = getAvailableLoyaltyRewards((int)$acc['balance']);
  jsonSuccess(['rewards'=>$rewards,'balance'=>(int)$acc['balance'],'account'=>$acc]);
} catch(Throwable $e){ jsonError('Erreur.',500); }
