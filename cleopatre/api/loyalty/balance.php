<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$user = requireAuth();
try {
  $pdo = db();
  $acc = getLoyaltyAccount((int)$user['id']);
  $rewards = getAvailableLoyaltyRewards((int)$acc['balance']);
  // recent transactions 5
  $stmt = $pdo->prepare("SELECT * FROM loyalty_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
  $stmt->execute([$user['id']]);
  $recent = $stmt->fetchAll();
  jsonSuccess([
    'account' => $acc,
    'rewards' => $rewards,
    'recent' => $recent,
    'rate' => (int)getSetting('loyalty_rate','10'),
    'rewardThreshold' => (int)getSetting('loyalty_reward_threshold','1000'),
    'rewardValue' => (int)getSetting('loyalty_reward_value','10000')
  ]);
} catch(Throwable $e){ appLog('error','loyalty balance',['e'=>$e->getMessage()]); jsonError('Erreur.',500); }
