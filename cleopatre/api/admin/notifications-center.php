<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
// synthesize notifications for admin dashboard
$n=[];
$n['new_order']=$pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at)=date('now')")->fetchColumn();
$n['low_stock']=$pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5")->fetchColumn();
$n['out_stock']=$pdo->query("SELECT COUNT(*) FROM products WHERE stock=0 OR (track_stock=1 AND stock_quantity<=0)")->fetchColumn();
$n['new_customer']=$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND date(created_at)=date('now')")->fetchColumn();
$n['review_waiting']=$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
$n['support_tickets']=$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','waiting')")->fetchColumn();
$n['promotion_expiring']=$pdo->query("SELECT COUNT(*) FROM promotions WHERE active=1 AND end_date IS NOT NULL AND date(end_date) BETWEEN date('now') AND date('now','+7 days')")->fetchColumn();
$n['contact_new']=$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
// recent events list
$recent=[];
$recentOrders=$pdo->query("SELECT 'new_order' as type, order_number as title, strftime('%d/%m %H:%M', created_at) as time, total FROM orders ORDER BY created_at DESC LIMIT 3")->fetchAll();
foreach($recentOrders as $r) $recent[]=['type'=>'commande','title'=>"Nouvelle commande {$r['title']}","body"=>number_format($r['total']/1000,3,',','')." DT · {$r['time']}",'link'=>"order.html?order_number={$r['title']}"];
$lowProds=$pdo->query("SELECT id, name, stock_quantity FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5 ORDER BY stock_quantity ASC LIMIT 2")->fetchAll();
foreach($lowProds as $p) $recent[]=['type'=>'stock','title'=>"Stock faible : {$p['name']}","body"=>"Il reste {$p['stock_quantity']} unité(s)",'link'=>"products.html?q={$p['id']}"];
$recentReviews=$pdo->query("SELECT r.id, p.name FROM reviews r JOIN products p ON p.id=r.product_id WHERE r.status='pending' LIMIT 2")->fetchAll();
foreach($recentReviews as $rv) $recent[]=['type'=>'review','title'=>"Avis à modérer : {$rv['name']}",'body'=>"En attente de validation",'link'=>"reviews.html"];
$tickets=$pdo->query("SELECT ticket_number, subject FROM support_tickets WHERE status IN ('open','waiting') ORDER BY updated_at DESC LIMIT 2")->fetchAll();
foreach($tickets as $t) $recent[]=['type'=>'support','title'=>"Ticket {$t['ticket_number']}",'body'=>$t['subject'],'link'=>"support.html"];

jsonSuccess(['counters'=>$n,'recent'=>$recent]);
