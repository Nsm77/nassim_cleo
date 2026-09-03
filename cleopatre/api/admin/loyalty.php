<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $q = trim($_GET['q'] ?? '');
  $page = max(1,(int)($_GET['page'] ?? 1));
  $per = min(50,max(1,(int)($_GET['per_page'] ?? 15)));
  if (isset($_GET['user_id'])) {
    $uid=(int)$_GET['user_id'];
    $acc = getLoyaltyAccount($uid);
    $stmt=$pdo->prepare("SELECT lt.*, o.order_number FROM loyalty_transactions lt LEFT JOIN orders o ON o.id=lt.order_id WHERE lt.user_id=? ORDER BY lt.created_at DESC LIMIT 20");
    $stmt->execute([$uid]);
    $tx=$stmt->fetchAll();
    $user=$pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id=? LIMIT 1");
    $user->execute([$uid]);
    jsonSuccess(['account'=>$acc,'transactions'=>$tx,'user'=>$user->fetch()]);
  }
  // list accounts with search
  $where="1=1"; $params=[];
  if($q!==''){
    $where=" (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? )";
    $like="%$q%";
    $params=[$like,$like,$like];
  }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM loyalty_accounts la JOIN users u ON u.id=la.user_id WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT la.*, u.first_name, u.last_name, u.email FROM loyalty_accounts la JOIN users u ON u.id=la.user_id WHERE $where ORDER BY la.balance DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  $rows=$stmt->fetchAll();
  // summary
  $totalPoints=$pdo->query("SELECT COALESCE(SUM(balance),0) FROM loyalty_accounts")->fetchColumn();
  $totalEarned=$pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_transactions WHERE type='earned'")->fetchColumn();
  $totalRedeemed=$pdo->query("SELECT COALESCE(ABS(SUM(points)),0) FROM loyalty_transactions WHERE type='redeemed'")->fetchColumn();
  jsonSuccess(['accounts'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)],'summary'=>['total_points'=>(int)$totalPoints,'total_earned'=>(int)$totalEarned,'total_redeemed'=>(int)$totalRedeemed]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  requireCsrf();
  $input=getJsonInput();
  if(empty($input)) $input=$_POST;
  $userId=(int)($input['user_id'] ?? 0);
  $points=(int)($input['points'] ?? 0);
  $reason=sanitizeString($input['reason'] ?? $input['reference'] ?? 'Ajustement administrateur',255);
  $type=$input['type'] ?? 'adjustment';
  if(!$userId || $points===0) jsonError('user_id et points requis.',422);
  if(!in_array($type,['adjustment','bonus','expired'])) $type='adjustment';
  // validate user exists
  $stmt=$pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$userId]);
  if(!$stmt->fetchColumn()) jsonError('Utilisateur introuvable.',404);
  if($points < -10000 || $points > 10000) jsonError('Montant invalide (max ±10000).',422);
  // if negative, check balance
  $acc=getLoyaltyAccount($userId);
  if($points <0 && (int)$acc['balance'] + $points <0) jsonError('Solde insuffisant.',400);
  $acc = loyaltyAddPoints($userId,$points,$type,null,$reason,(int)$admin['id']);
  adminLog((int)$admin['id'],'loyalty_adjustment','user',(string)$userId,['points'=>$points,'reason'=>$reason,'type'=>$type]);
  jsonSuccess(['account'=>$acc],'Ajustement effectué.');
}
jsonError('Méthode non autorisée.',405);
