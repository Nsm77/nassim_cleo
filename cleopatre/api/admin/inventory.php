<?php
require __DIR__ . '/../_bootstrap.php';
$admin=requireAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $filter=trim($_GET['filter'] ?? '');
  $q=trim($_GET['q'] ?? '');
  $page=max(1,(int)($_GET['page'] ?? 1));
  $per=min(50,max(1,(int)($_GET['per_page'] ?? 20)));
  $where="1=1"; $params=[];
  if($filter==='low') $where.=" AND p.track_stock=1 AND p.stock_quantity>0 AND p.stock_quantity<=5";
  elseif($filter==='out') $where.=" AND (p.stock=0 OR (p.track_stock=1 AND p.stock_quantity<=0))";
  elseif($filter==='in') $where.=" AND p.stock=1";
  elseif($filter==='fast'){
    // fast sellers: order by qty sold DESC
    // handled via order
  } elseif($filter==='slow'){
    // slow sellers ordered ASC
  }
  if($q!==''){ $where.=" AND (p.id LIKE ? OR p.name LIKE ? OR p.brand LIKE ?)"; $like="%$q%"; $params[]=$like;$params[]=$like;$params[]=$like; }
  // stats
  $total=$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  $inStock=$pdo->query("SELECT COUNT(*) FROM products WHERE stock=1 AND (track_stock=0 OR stock_quantity>0)")->fetchColumn();
  $lowStock=$pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5")->fetchColumn();
  $outStock=$pdo->query("SELECT COUNT(*) FROM products WHERE stock=0 OR (track_stock=1 AND stock_quantity<=0)")->fetchColumn();
  $recentRestocked=$pdo->query("SELECT COUNT(DISTINCT product_id) FROM inventory_movements WHERE change_qty>0 AND date(created_at) >= date('now','-7 days')")->fetchColumn();

  $order="p.name ASC";
  if($filter==='fast') $order="(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status IN ('delivered','confirmed','preparing','shipped')) DESC";
  if($filter==='slow') $order="(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status IN ('delivered','confirmed','preparing','shipped')) ASC";

  $cnt=$pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
  $cnt->execute($params);
  $totalFiltered=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT p.*, (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status IN ('delivered','confirmed','preparing','shipped')) as sold_qty FROM products p WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  $products=$stmt->fetchAll();

  // recent movements quick preview
  $recent=$pdo->query("SELECT m.*, p.name as product_name FROM inventory_movements m LEFT JOIN products p ON p.id=m.product_id ORDER BY m.created_at DESC LIMIT 10")->fetchAll();

  jsonSuccess([
    'stats'=>['total'=>(int)$total,'in_stock'=>(int)$inStock,'low_stock'=>(int)$lowStock,'out_stock'=>(int)$outStock,'recent_restocked'=>(int)$recentRestocked],
    'products'=>$products,
    'recent_movements'=>$recent,
    'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$totalFiltered,'total_pages'=>ceil($totalFiltered/max(1,$per))]
  ]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $productId=trim($input['product_id'] ?? $input['id'] ?? '');
  $qty=isset($input['stock_quantity']) ? (int)$input['stock_quantity'] : null;
  $change=isset($input['change_qty']) ? (int)$input['change_qty'] : null;
  $reason=trim($input['reason'] ?? 'manual');
  $allowedReasons=['sale','restock','manual_correction','damaged','returned','other','admin_adjust','bulk_adjust'];
  if(!$productId) jsonError('product_id requis.',422);
  $stmt=$pdo->prepare("SELECT * FROM products WHERE id=?");
  $stmt->execute([$productId]);
  $p=$stmt->fetch();
  if(!$p) jsonError('Produit introuvable.',404);
  $oldQty=(int)$p['stock_quantity'];
  $newQty=$oldQty;
  if($qty!==null) $newQty=$qty;
  elseif($change!==null) $newQty=$oldQty+$change;
  else jsonError('stock_quantity ou change_qty requis.',422);
  if($newQty<0) $newQty=0;
  $diff=$newQty-$oldQty;
  if(!in_array($reason,$allowedReasons)) $reason='other';
  $pdo->prepare("UPDATE products SET stock_quantity=?, stock=?, track_stock=1, updated_at=? WHERE id=?")->execute([$newQty,$newQty>0?1:0,now(),$productId]);
  $pdo->prepare("INSERT INTO inventory_movements(product_id, change_qty, previous_qty, new_qty, reason, admin_id, reference) VALUES (?,?,?,?,?,?,?)")->execute([$productId,$diff,$oldQty,$newQty,$reason,(int)$admin['id'], trim($input['reference']??'')]);
  adminLog((int)$admin['id'],'inventory_adjust','product',$productId,['from'=>$oldQty,'to'=>$newQty,'diff'=>$diff,'reason'=>$reason]);
  $row=$pdo->prepare("SELECT * FROM products WHERE id=?"); $row->execute([$productId]);
  jsonSuccess(['product'=>$row->fetch(),'diff'=>$diff],'Stock mis à jour.');
}
jsonError('Méthode non autorisée.',405);
