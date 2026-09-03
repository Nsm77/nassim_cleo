<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $q = trim($_GET['q'] ?? '');
  $status = trim($_GET['status'] ?? '');
  $page = max(1,(int)($_GET['page'] ?? 1));
  $per = min(50,max(1,(int)($_GET['per_page'] ?? 15)));
  $where = "1=1";
  $params=[];
  if ($status && in_array($status,['pending','confirmed','preparing','shipped','delivered','cancelled'])) { $where.=" AND o.status=?"; $params[]=$status; }
  if ($q!=='') {
    $where .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like="%$q%";
    $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like;
  }
  // date filter?
  $dateFrom = $_GET['from'] ?? null;
  $dateTo = $_GET['to'] ?? null;
  if ($dateFrom) { $where.=" AND date(o.created_at) >= date(?)"; $params[]=$dateFrom; }
  if ($dateTo) { $where.=" AND date(o.created_at) <= date(?)"; $params[]=$dateTo; }

  $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone FROM orders o JOIN users u ON u.id=o.user_id WHERE $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  $rows=$stmt->fetchAll();
  jsonSuccess(['orders'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  requireCsrf();
  $input=getJsonInput();
  if(empty($input)) $input=$_POST;
  $orderId=(int)($input['order_id'] ?? 0);
  $newStatus=trim($input['status'] ?? '');
  $note=sanitizeString($input['note'] ?? '',500);
  $allowed=['pending','confirmed','preparing','shipped','delivered','cancelled'];
  if(!$orderId || !in_array($newStatus,$allowed)) jsonError('Statut invalide.',422);
  $stmt=$pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
  $stmt->execute([$orderId]);
  $order=$stmt->fetch();
  if(!$order) jsonError('Commande introuvable.',404);
  // progression normale
  $orderFlow=['pending'=>0,'confirmed'=>1,'preparing'=>2,'shipped'=>3,'delivered'=>4,'cancelled'=>99];
  $curr=$order['status'];
  // permettre cancel à tout moment sauf delivered
  if($newStatus==='cancelled'){
    if($curr==='delivered') jsonError('Commande livrée, annulation impossible.',400);
    if($curr==='cancelled') jsonError('Déjà annulée.',400);
  } else if ($newStatus!=='cancelled') {
    // vérifier transition vers l'avant seulement (pas revenir en arrière sauf cancel)
    if($orderFlow[$newStatus] < $orderFlow[$curr]) jsonError('Transition invalide : retour en arrière non autorisé.',400);
    if($orderFlow[$newStatus] !== $orderFlow[$curr]+1 && $orderFlow[$curr]!==99) {
      // permettre saut? On exige étape suivante uniquement, sauf si admin force via note?
      // Tolérance: permettre 1 step only
      jsonError('Progression invalide. Utilisez Étape suivante.',400);
    }
  }

  $pdo->beginTransaction();
  $pdo->prepare("UPDATE orders SET status=?, updated_at=? WHERE id=?")->execute([$newStatus,now(),$orderId]);
  if($newStatus==='shipped'){
    $pdo->prepare("UPDATE orders SET shipped_at=? WHERE id=?")->execute([now(),$orderId]);
  }
  if($newStatus==='delivered'){
    $pdo->prepare("UPDATE orders SET delivered_at=? WHERE id=?")->execute([now(),$orderId]);
  }
  $pdo->prepare("INSERT INTO order_tracking(order_id,status,note,created_by) VALUES (?,?,?,?)")->execute([$orderId,$newStatus,$note?:null,$admin['id']]);

  // Loyalty: earn on delivered, refund on cancelled
  try {
    if ($newStatus==='delivered' && $order['status']!=='delivered') {
      // award points: if not already awarded
      $check=$pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE order_id=? AND type='earned'");
      $check->execute([$orderId]);
      if ((int)$check->fetchColumn()===0) {
        $points = (int)($order['loyalty_points_earned'] ?? 0);
        if ($points <=0) {
          // fallback calculation
          $points = calculateLoyaltyPoints(max(0, (int)$order['subtotal'] - (int)$order['discount'] - (int)($order['loyalty_discount'] ?? 0)));
        }
        if ($points>0) {
          loyaltyAddPoints((int)$order['user_id'],$points,'earned',$orderId,'Commande '.$order['order_number'].' livrée', (int)$admin['id']);
          $pdo->prepare("UPDATE orders SET loyalty_points_earned=? WHERE id=?")->execute([$points,$orderId]);
          createNotification((int)$order['user_id'],'order','Commande livrée — +'.$points.' points fidélité',"Votre commande ".$order['order_number']." est livrée. Vous avez gagné ".$points." points.", "pages/order.html?order_number=".$order['order_number']);
          createNotification((int)$order['user_id'],'loyalty','+'.$points.' points gagnés',"Merci pour votre fidélité. Solde mis à jour.", "pages/account.html#fidélité");
        }
      }
    }
    if ($newStatus==='cancelled') {
      // refund loyalty points if they were used
      $used = (int)($order['loyalty_points_used'] ?? 0);
      if ($used>0) {
        $check=$pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE order_id=? AND type='refund'");
        $check->execute([$orderId]);
        if ((int)$check->fetchColumn()===0) {
          loyaltyAddPoints((int)$order['user_id'],$used,'refund',$orderId,'Remboursement fidélité annulation '.$order['order_number'], (int)$admin['id']);
          createNotification((int)$order['user_id'],'loyalty',$used.' points remboursés',"Votre commande ".$order['order_number']." a été annulée. Vos points ont été recrédités.", "pages/account.html#fidélité");
        }
      }
      // if already earned but order cancelled after delivered should not happen, but handle reversal
      $earnedCheck=$pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE order_id=? AND type='earned'");
      $earnedCheck->execute([$orderId]);
      if ((int)$earnedCheck->fetchColumn()>0) {
        $earned = (int)($order['loyalty_points_earned'] ?? 0);
        if ($earned>0) {
          loyaltyAddPoints((int)$order['user_id'],-$earned,'adjustment',$orderId,'Annulation — retrait points '.$order['order_number'], (int)$admin['id']);
        }
      }
      // restore stock if track_stock
      $items=$pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
      $items->execute([$orderId]);
      foreach($items->fetchAll() as $it){
        $p=$pdo->prepare("SELECT track_stock FROM products WHERE id=?");
        $p->execute([$it['product_id']]);
        if((int)$p->fetchColumn()===1){
          $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, stock=1, updated_at=? WHERE id=?")->execute([$it['quantity'],now(),$it['product_id']]);
          $pdo->prepare("INSERT INTO inventory_movements(product_id, change_qty, reason, order_id, admin_id) VALUES (?, ?, 'cancel_restore', ?, ?)")->execute([$it['product_id'],$it['quantity'],$orderId,$admin['id']]);
        }
      }
    }
    if (in_array($newStatus,['confirmed','preparing','shipped'])) {
      $titles=['confirmed'=>'Commande confirmée','preparing'=>'Commande en préparation','shipped'=>'Commande expédiée'];
      $bodies=['confirmed'=>'Votre commande '.$order['order_number'].' a été confirmée.','preparing'=>'Votre commande est en cours de préparation.','shipped'=>'Votre commande a été expédiée. Suivi disponible.'];
      createNotification((int)$order['user_id'],'order',$titles[$newStatus],$bodies[$newStatus],"pages/order.html?order_number=".$order['order_number']);
    }
  } catch(Throwable $e){ appLog('error','loyalty hook failed',['e'=>$e->getMessage()]); }

  $pdo->commit();
  adminLog((int)$admin['id'],'order_status_change','order',(string)$orderId,['from'=>$curr,'to'=>$newStatus,'note'=>$note]);
  appLog('info','order status change',['order_id'=>$orderId,'from'=>$curr,'to'=>$newStatus,'admin'=>$admin['email']]);
  $updated=$pdo->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
  jsonSuccess(['order'=>$updated],'Statut mis à jour.');
}
jsonError('Méthode non autorisée.',405);
