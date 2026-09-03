<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$type=trim($_GET['type'] ?? 'orders');
$from=trim($_GET['from'] ?? '');
$to=trim($_GET['to'] ?? '');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cleopatre-'.$type.'-'.date('Ymd').'.csv"');
$out=fopen('php://output','w');
if($type==='orders'){
  fputcsv($out,['order_number','date','customer','email','total','status','payment','subtotal','discount','shipping']);
  $q="SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE 1=1";
  $params=[];
  if($from){ $q.=" AND date(o.created_at) >= date(?)"; $params[]=$from; }
  if($to){ $q.=" AND date(o.created_at) <= date(?)"; $params[]=$to; }
  $q.=" ORDER BY o.created_at DESC";
  $stmt=$pdo->prepare($q); $stmt->execute($params);
  foreach($stmt->fetchAll() as $o){
    fputcsv($out,[$o['order_number'],$o['created_at'],$o['first_name'].' '.$o['last_name'],$o['email'],$o['total'],$o['status'],$o['payment_method'],$o['subtotal'],$o['discount'],$o['shipping']]);
  }
} elseif($type==='customers'){
  fputcsv($out,['id','first_name','last_name','email','phone','status','created_at','orders','total_spent']);
  $stmt=$pdo->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) as orders, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id AND o.status IN ('delivered','confirmed','preparing','shipped')) as total FROM users u WHERE u.role='customer' ORDER BY u.created_at DESC");
  foreach($stmt->fetchAll() as $u){
    fputcsv($out,[$u['id'],$u['first_name'],$u['last_name'],$u['email'],$u['phone'],$u['status'],$u['created_at'],$u['orders'],$u['total']]);
  }
} elseif($type==='products'){
  fputcsv($out,['id','name','brand','cat','price','old_price','stock','featured','bestseller','active','stock_quantity']);
  foreach($pdo->query("SELECT * FROM products ORDER BY name ASC") as $p){
    fputcsv($out,[$p['id'],$p['name'],$p['brand'],$p['cat'],$p['price'],$p['old_price'],$p['stock'],$p['featured'],$p['bestseller'],$p['active'],$p['stock_quantity']]);
  }
} elseif($type==='inventory'){
  fputcsv($out,['product_id','product_name','change','previous','new','reason','admin','date']);
  $stmt=$pdo->query("SELECT m.*, p.name as product_name, u.email as admin_email FROM inventory_movements m LEFT JOIN products p ON p.id=m.product_id LEFT JOIN users u ON u.id=m.admin_id ORDER BY m.created_at DESC LIMIT 1000");
  foreach($stmt->fetchAll() as $m){
    fputcsv($out,[$m['product_id'],$m['product_name'],$m['change_qty'],$m['previous_qty'],$m['new_qty'],$m['reason'],$m['admin_email'],$m['created_at']]);
  }
} elseif($type==='loyalty'){
  fputcsv($out,['id','user','email','type','points','balance_after','reference','date']);
  $stmt=$pdo->query("SELECT lt.*, u.email, u.first_name, u.last_name FROM loyalty_transactions lt JOIN users u ON u.id=lt.user_id ORDER BY lt.created_at DESC LIMIT 1000");
  foreach($stmt->fetchAll() as $l){
    fputcsv($out,[$l['id'],$l['first_name'].' '.$l['last_name'],$l['email'],$l['type'],$l['points'],$l['balance_after'],$l['reference'],$l['created_at']]);
  }
} else {
  fputcsv($out,['error','Type invalide']);
}
fclose($out);
exit;
