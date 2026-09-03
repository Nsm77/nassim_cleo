<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // list or detail
  if (isset($_GET['id'])) {
    $id=(int)$_GET['id'];
    $stmt=$pdo->prepare("SELECT id, uuid, first_name, last_name, email, phone, role, status, created_at, last_login_at FROM users WHERE id=? AND role='customer' LIMIT 1");
    $stmt->execute([$id]);
    $user=$stmt->fetch();
    if(!$user) jsonError('Client introuvable.',404);
    $orders=$pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
    $orders->execute([$id]);
    $addrs=$pdo->prepare("SELECT * FROM user_addresses WHERE user_id=?");
    $addrs->execute([$id]);
    $wishCount=$pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
    $wishCount->execute([$id]);
    $orderCount=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
    $orderCount->execute([$id]);
    $totalSpent=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status IN ('delivered','confirmed','preparing','shipped')");
    $totalSpent->execute([$id]);
    $lastOrder=$pdo->prepare("SELECT created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $lastOrder->execute([$id]);
    jsonSuccess([
      'customer'=>$user,
      'addresses'=>$addrs->fetchAll(),
      'orders'=>$orders->fetchAll(),
      'stats'=>[
        'order_count'=>(int)$orderCount->fetchColumn(),
        'total_spent'=>(int)$totalSpent->fetchColumn(),
        'wishlist_count'=>(int)$wishCount->fetchColumn(),
        'last_order'=>$lastOrder->fetchColumn()
      ]
    ]);
  } else {
    $q=trim($_GET['q'] ?? '');
    $page=max(1,(int)($_GET['page'] ?? 1));
    $per=min(50,max(1,(int)($_GET['per_page'] ?? 15)));
    $where="role='customer'";
    $params=[];
    if($q!==''){
      $where.=" AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
      $like="%$q%";
      $params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;
    }
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
    $cnt->execute($params);
    $total=(int)$cnt->fetchColumn();
    $offset=($page-1)*$per;
    $stmt=$pdo->prepare("SELECT id, uuid, first_name, last_name, email, phone, status, created_at, last_login_at FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params,[$per,$offset]));
    $rows=$stmt->fetchAll();
    // enrich
    foreach($rows as &$r){
      $c=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
      $c->execute([$r['id']]);
      $r['order_count']=(int)$c->fetchColumn();
      $s=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status IN ('delivered','confirmed','preparing','shipped')");
      $s->execute([$r['id']]);
      $r['total_spent']=(int)$s->fetchColumn();
    }
    jsonSuccess(['customers'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  requireCsrf();
  $input=getJsonInput();
  if(empty($input)) $input=$_POST;
  $id=(int)($input['id'] ?? 0);
  $action=trim($input['action'] ?? '');
  $allowed=['disable','enable','suspend','activate','suspended','active','disabled'];
  if(!$id || !in_array($action,$allowed)) jsonError('Action invalide.',422);
  // normalize
  $map=['disable'=>'disabled','suspended'=>'suspended','suspend'=>'suspended','enable'=>'active','activate'=>'active','active'=>'active','disabled'=>'disabled'];
  $newStatus=$map[$action] ?? $action;
  if(!in_array($newStatus,['active','suspended','disabled'])) $newStatus='disabled';
  $stmt=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='customer' LIMIT 1");
  $stmt->execute([$id]);
  $u=$stmt->fetch();
  if(!$u) jsonError('Client introuvable.',404);
  try {
    $pdo->prepare("UPDATE users SET status=?, updated_at=? WHERE id=?")->execute([$newStatus,now(),$id]);
  } catch(Throwable $e){
    // fallback if constraint still old (no suspended) — map suspended -> disabled
    if($newStatus==='suspended'){
      $pdo->prepare("UPDATE users SET status='disabled', updated_at=? WHERE id=?")->execute([now(),$id]);
      $newStatus='disabled (suspended)';
    } else throw $e;
  }
  adminLog((int)$admin['id'],'customer_status_change','user',(string)$id,['from'=>$u['status'],'to'=>$newStatus]);
  $msg=$newStatus==='active'?'Compte réactivé.':($newStatus==='suspended'?'Compte suspendu.':'Compte désactivé.');
  jsonSuccess(['status'=>$newStatus], $msg);
}

jsonError('Méthode non autorisée.',405);
