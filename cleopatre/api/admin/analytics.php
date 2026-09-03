<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$period=trim($_GET['period'] ?? '30d');
$customFrom=trim($_GET['from'] ?? '');
$customTo=trim($_GET['to'] ?? '');
$where="1=1"; $params=[];
$label='30 jours';
if($customFrom && $customTo){
  $where.=" AND date(o.created_at) BETWEEN date(?) AND date(?)";
  $params[]=$customFrom; $params[]=$customTo;
  $label="$customFrom → $customTo";
} else {
  switch($period){
    case 'today': $where.=" AND date(o.created_at)=date('now')"; $label="Aujourd’hui"; break;
    case 'yesterday': $where.=" AND date(o.created_at)=date('now','-1 day')"; $label="Hier"; break;
    case '7d': $where.=" AND date(o.created_at) >= date('now','-7 days')"; $label="7 jours"; break;
    case '30d': $where.=" AND date(o.created_at) >= date('now','-30 days')"; break;
    case '90d': $where.=" AND date(o.created_at) >= date('now','-90 days')"; break;
    case 'year': $where.=" AND strftime('%Y',o.created_at)=strftime('%Y','now')"; $label="Année"; break;
    case 'all': $where="1=1"; $label="Tout"; break;
  }
}
$statusFilter=" AND o.status IN ('delivered','confirmed','preparing','shipped')";
// Sales analytics
$sales=$pdo->prepare("SELECT COALESCE(SUM(o.total),0) as revenue, COUNT(*) as orders, AVG(o.total) as avg_order, COALESCE(SUM(oi_sum.qty),0) as units FROM orders o LEFT JOIN (SELECT order_id, SUM(quantity) as qty FROM order_items GROUP BY order_id) oi_sum ON oi_sum.order_id=o.id WHERE $where $statusFilter");
$sales->execute($params);
$salesRow=$sales->fetch();
$revenue=(int)$salesRow['revenue'];
$ordersCnt=(int)$salesRow['orders'];
$avgOrder=(int)($salesRow['avg_order']?:0);
$units=(int)$salesRow['units'];
// daily breakdown for last 30 days
$daily=[];
try{
  $dailyStmt=$pdo->prepare("SELECT date(o.created_at) as d, COUNT(*) as orders, SUM(o.total) as revenue FROM orders o WHERE date(o.created_at) >= date('now','-30 days') $statusFilter GROUP BY date(o.created_at) ORDER BY d ASC");
  $dailyStmt->execute();
  $daily=$dailyStmt->fetchAll();
}catch(Throwable $e){}
// Product analytics
$bestSellers=$pdo->prepare("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as qty, SUM(oi.price*oi.quantity) as revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE date(o.created_at) >= date('now','-90 days') AND o.status IN ('delivered','confirmed','preparing','shipped') GROUP BY oi.product_id ORDER BY qty DESC LIMIT 10");
$bestSellers->execute();
$best=$bestSellers->fetchAll();
$worst=$pdo->query("SELECT p.id, p.name, COALESCE(SUM(oi.quantity),0) as qty FROM products p LEFT JOIN order_items oi ON oi.product_id=p.id LEFT JOIN orders o ON o.id=oi.order_id AND o.status IN ('delivered','confirmed','preparing','shipped') GROUP BY p.id ORDER BY qty ASC LIMIT 10")->fetchAll();
$mostViewed=[]; try{ $mostViewed=$pdo->query("SELECT p.id, p.name, COUNT(rv.id) as views FROM products p LEFT JOIN recently_viewed rv ON rv.product_id=p.id GROUP BY p.id ORDER BY views DESC LIMIT 5")->fetchAll(); }catch(Throwable $e){}
$lowStock=$pdo->query("SELECT id, name, stock_quantity FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5 ORDER BY stock_quantity ASC LIMIT 10")->fetchAll();
$outStock=$pdo->query("SELECT id, name FROM products WHERE stock=0 OR (track_stock=1 AND stock_quantity<=0) LIMIT 10")->fetchAll();
// Customer analytics
$new=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role='customer' AND date(created_at) >= date('now','-30 days')"); $new->execute(); $newCnt=(int)$new->fetchColumn();
$returning=$pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE status IN ('delivered','confirmed','preparing','shipped') GROUP BY user_id HAVING COUNT(*) >1")->fetchAll(); $retCnt=count($returning);
$vip=$pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, SUM(o.total) as total, COUNT(o.id) as orders FROM users u JOIN orders o ON o.user_id=u.id WHERE o.status IN ('delivered','confirmed','preparing','shipped') GROUP BY u.id HAVING total > 500000 ORDER BY total DESC LIMIT 10")->fetchAll(); // >500 DT
$highValue=$pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, SUM(o.total) as total FROM users u JOIN orders o ON o.user_id=u.id WHERE o.status IN ('delivered','confirmed','preparing','shipped') GROUP BY u.id ORDER BY total DESC LIMIT 10")->fetchAll();
// Promotion analytics
$promoUsage=$pdo->query("SELECT p.code, COUNT(pu.id) as usage_count, COALESCE(SUM(o.discount),0) as discount_total, COUNT(DISTINCT o.id) as orders FROM promotions p LEFT JOIN promotion_usage pu ON pu.promotion_id=p.id LEFT JOIN orders o ON o.id=pu.order_id GROUP BY p.id ORDER BY usage_count DESC LIMIT 10")->fetchAll();
// Loyalty analytics
$pointsIssued=$pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_transactions WHERE type='earned'")->fetchColumn();
$pointsRedeemed=$pdo->query("SELECT COALESCE(ABS(SUM(points)),0) FROM loyalty_transactions WHERE type='redeemed'")->fetchColumn();
$rewardsUsed=$pdo->query("SELECT COUNT(*) FROM loyalty_transactions WHERE type='redeemed'")->fetchColumn();
$outstanding=$pdo->query("SELECT COALESCE(SUM(balance),0) FROM loyalty_accounts")->fetchColumn();

jsonSuccess([
  'period'=>$label,
  'sales'=>['revenue'=>$revenue,'orders'=>$ordersCnt,'avg_order'=>$avgOrder,'units'=>$units,'daily'=>$daily],
  'products'=>['best_sellers'=>$best,'worst_performers'=>$worst,'most_viewed'=>$mostViewed,'low_stock'=>$lowStock,'out_stock'=>$outStock],
  'customers'=>['new_30d'=>$newCnt,'returning'=>$retCnt,'vip'=>$vip,'high_value'=>$highValue],
  'promotions'=>$promoUsage,
  'loyalty'=>['issued'=>(int)$pointsIssued,'redeemed'=>(int)$pointsRedeemed,'outstanding'=>(int)$outstanding,'rewards_used'=>(int)$rewardsUsed]
]);
