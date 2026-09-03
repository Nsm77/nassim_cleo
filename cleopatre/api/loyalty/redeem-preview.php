<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
$user = requireAuth();
requireCsrf();
$input = getJsonInput();
if(empty($input)) $input=$_POST;
$rewardId = (int)($input['reward_id'] ?? 0);
$subtotal = isset($input['subtotal']) ? (int)$input['subtotal'] : null;
try {
  $pdo = db();
  $acc = getLoyaltyAccount((int)$user['id']);
  if ($rewardId) {
    $stmt=$pdo->prepare("SELECT * FROM loyalty_rewards WHERE id=? AND active=1 LIMIT 1");
    $stmt->execute([$rewardId]);
    $r=$stmt->fetch();
    if(!$r) jsonError('Récompense introuvable.',404);
    $eligible = (int)$acc['balance'] >= (int)$r['points_cost'];
    jsonSuccess(['reward'=>$r,'eligible'=>$eligible,'balance'=>(int)$acc['balance'],'discount'=>(int)$r['discount_value']]);
  } else {
    // default 1000 => 10 DT
    $cost = (int)getSetting('loyalty_reward_threshold','1000');
    $val = (int)getSetting('loyalty_reward_value','10000');
    $eligible = (int)$acc['balance'] >= $cost;
    jsonSuccess(['reward'=>['id'=>0,'code'=>'REWARD10','name'=>'Bon 10 DT','points_cost'=>$cost,'discount_value'=>$val],'eligible'=>$eligible,'balance'=>(int)$acc['balance'],'discount'=>$val]);
  }
} catch(Throwable $e){ jsonError('Erreur.',500); }
