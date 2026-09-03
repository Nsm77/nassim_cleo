<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.',405);
$admin = requireSuperAdmin();
try {
  $pdo = db();
  $period = $_GET['period'] ?? 'all';
  $allowedPeriods=['today','7d','30d','90d','all'];
  if(!in_array($period,$allowedPeriods)) $period='all';
  $revenueStatuses = "'delivered','confirmed','preparing','shipped'";
  $rev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses)")->fetchColumn();
  $todayRev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at)=date('now')")->fetchColumn();
  $weekRev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at) >= date('now','-7 days')")->fetchColumn();
  $w30Rev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at) >= date('now','-30 days')")->fetchColumn();
  $w90Rev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at) >= date('now','-90 days')")->fetchColumn();
  $monthRev = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND strftime('%Y-%m',created_at)=strftime('%Y-%m','now')")->fetchColumn();
  // period filtered
  $wherePeriod="";
  if($period==='today') $wherePeriod=" AND date(created_at)=date('now')";
  elseif($period==='7d') $wherePeriod=" AND date(created_at) >= date('now','-7 days')";
  elseif($period==='30d') $wherePeriod=" AND date(created_at) >= date('now','-30 days')";
  elseif($period==='90d') $wherePeriod=" AND date(created_at) >= date('now','-90 days')";
  $totalOrdersPeriod = $pdo->query("SELECT COUNT(*) FROM orders WHERE 1=1 $wherePeriod")->fetchColumn();
  $revPeriod = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) $wherePeriod")->fetchColumn();
  $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
  $totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
  $newCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND date(created_at) >= date('now','-7 days')")->fetchColumn();
  $returningCustomers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE status IN ($revenueStatuses) GROUP BY user_id HAVING COUNT(*) >1")->fetchAll();
  $returningCount = count($returningCustomers);
  $avg = $pdo->query("SELECT AVG(total) FROM orders WHERE status IN ($revenueStatuses)")->fetchColumn();
  $avgPeriod = $pdo->query("SELECT AVG(total) FROM orders WHERE status IN ($revenueStatuses) $wherePeriod")->fetchColumn();
  $productsCount = $pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
  $productsActive = $productsCount;
  $productsTotal = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  $productsInactive = (int)$productsTotal - (int)$productsActive;
  $featuredCount = $pdo->query("SELECT COUNT(*) FROM products WHERE featured=1 AND active=1")->fetchColumn();
  $promotionalCount = $pdo->query("SELECT COUNT(*) FROM products WHERE (promo_active=1 OR (old_price IS NOT NULL AND old_price > price)) AND active=1")->fetchColumn();
  $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5")->fetchColumn();
  $outStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock=0 OR (track_stock=1 AND stock_quantity<=0)")->fetchColumn();
  $activeCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND status='active'")->fetchColumn();
  $disabledCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND status='disabled'")->fetchColumn();
  $suspendedCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND status='suspended'")->fetchColumn();
  $cancelledOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetchColumn();
  $confirmedOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='confirmed'")->fetchColumn();
  $preparingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='preparing'")->fetchColumn();
  $shippedOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='shipped'")->fetchColumn();
  $deliveredOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn();
  $promotionsActive = $pdo->query("SELECT COUNT(*) FROM promotions WHERE active=1")->fetchColumn();
  $promotionsTotal = $pdo->query("SELECT COUNT(*) FROM promotions")->fetchColumn();
  $topProducts=$pdo->query("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as qty, SUM(oi.price*oi.quantity) as revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status IN ($revenueStatuses) GROUP BY oi.product_id ORDER BY qty DESC LIMIT 5")->fetchAll();
  // loyalty activity
  $loyEarned=$pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_transactions WHERE type='earned'")->fetchColumn();
  $loyRedeemed=$pdo->query("SELECT COALESCE(ABS(SUM(points)),0) FROM loyalty_transactions WHERE type='redeemed'")->fetchColumn();
  $loyToday=$pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_transactions WHERE type='earned' AND date(created_at)=date('now')")->fetchColumn();
  $loyYesterday=$pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_transactions WHERE type='earned' AND date(created_at)=date('now','-1 day')")->fetchColumn();
  // today vs yesterday comps
  $todayRevYesterday=$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at)=date('now','-1 day')")->fetchColumn();
  $todayOrders=$pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at)=date('now')")->fetchColumn();
  $yesterdayOrders=$pdo->query("SELECT COUNT(*) FROM orders WHERE date(created_at)=date('now','-1 day')")->fetchColumn();
  $newCustomersToday=$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND date(created_at)=date('now')")->fetchColumn();
  $newCustomersYesterday=$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND date(created_at)=date('now','-1 day')")->fetchColumn();
  $waitingOrders=$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','preparing')")->fetchColumn();
  $toConfirm=$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
  $toPrepare=$pdo->query("SELECT COUNT(*) FROM orders WHERE status='confirmed'")->fetchColumn();
  $toShip=$pdo->query("SELECT COUNT(*) FROM orders WHERE status='preparing'")->fetchColumn();
  $productsSoldToday=$pdo->query("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE date(o.created_at)=date('now') AND o.status IN ($revenueStatuses)")->fetchColumn();
  $productsSoldYesterday=$pdo->query("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE date(o.created_at)=date('now','-1 day') AND o.status IN ($revenueStatuses)")->fetchColumn();
  $avgToday=$pdo->query("SELECT AVG(total) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at)=date('now')")->fetchColumn();
  $avgYesterday=$pdo->query("SELECT AVG(total) FROM orders WHERE status IN ($revenueStatuses) AND date(created_at)=date('now','-1 day')")->fetchColumn();
  // par statut orders
  $byStatus = $pdo->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
  // recent orders
  $recent = $pdo->query("SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 6")->fetchAll();
  // actions à faire
  $lowStockProducts=$pdo->query("SELECT id, name, stock_quantity FROM products WHERE track_stock=1 AND stock_quantity>0 AND stock_quantity<=5 ORDER BY stock_quantity ASC LIMIT 5")->fetchAll();
  $outStockProducts=$pdo->query("SELECT id, name FROM products WHERE stock=0 OR (track_stock=1 AND stock_quantity<=0) LIMIT 5")->fetchAll();
  $pendingReviews=$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
  $pendingTickets=$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','waiting')")->fetchColumn();
  $pendingContacts=$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
  $recentDisabled=$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND status='disabled' AND date(updated_at) >= date('now','-7 days')")->fetchColumn();
  $expiringPromos=$pdo->query("SELECT COUNT(*) FROM promotions WHERE active=1 AND end_date IS NOT NULL AND date(end_date) BETWEEN date('now') AND date('now','+7 days')")->fetchColumn();
  $activeFlash=$pdo->query("SELECT COUNT(*) FROM flash_sales WHERE active=1 AND date('now') BETWEEN date(start_date) AND date(end_date)")->fetchColumn();

  function trend($today,$yesterday){
    if($yesterday==0 && $today==0) return ['dir'=>'→','pct'=>0];
    if($yesterday==0) return ['dir'=>'↑','pct'=>100];
    $pct=round(($today-$yesterday)/$yesterday*100);
    $dir=$pct>0?'↑':($pct<0?'↓':'→');
    return ['dir'=>$dir,'pct'=>abs($pct)];
  }

  jsonSuccess([
    'revenue'=>[
      'total'=>(int)$rev,
      'today'=>(int)$todayRev,
      'yesterday'=>(int)$todayRevYesterday,
      'trend'=>trend((int)$todayRev,(int)$todayRevYesterday),
      'week'=>(int)$weekRev,
      'month'=>(int)$monthRev,
      'w30'=>(int)$w30Rev,
      'w90'=>(int)$w90Rev,
      'period'=>['key'=>$period,'revenue'=>(int)$revPeriod,'orders'=>(int)$totalOrdersPeriod,'avg'=>(int)($avgPeriod?:0)],
      'rule'=>"Compté : delivered + confirmed + preparing + shipped. Annulées et en attente exclues."
    ],
    'orders'=>[
      'total'=>(int)$totalOrders,
      'today'=>(int)$todayOrders,
      'yesterday'=>(int)$yesterdayOrders,
      'trend'=>trend((int)$todayOrders,(int)$yesterdayOrders),
      'pending'=>(int)$pending,
      'confirmed'=>(int)$confirmedOrders,
      'preparing'=>(int)$preparingOrders,
      'shipped'=>(int)$shippedOrders,
      'delivered'=>(int)$deliveredOrders,
      'cancelled'=>(int)$cancelledOrders,
      'waiting'=>(int)$waitingOrders,
      'by_status'=>$byStatus,
      'avg'=>(int)($avg?:0),
      'avg_today'=>(int)($avgToday?:0),
      'avg_yesterday'=>(int)($avgYesterday?:0),
      'avg_trend'=>trend((int)($avgToday?:0),(int)($avgYesterday?:0))
    ],
    'customers'=>[
      'total'=>(int)$totalCustomers,
      'active'=>(int)$activeCustomers,
      'disabled'=>(int)$disabledCustomers,
      'suspended'=>(int)$suspendedCustomers,
      'new_week'=>(int)$newCustomers,
      'new_today'=>(int)$newCustomersToday,
      'new_yesterday'=>(int)$newCustomersYesterday,
      'new_trend'=>trend((int)$newCustomersToday,(int)$newCustomersYesterday),
      'returning'=>(int)$returningCount
    ],
    'products'=>[
      'total'=>(int)$productsTotal,
      'active'=>(int)$productsActive,
      'inactive'=>(int)$productsInactive,
      'featured'=>(int)$featuredCount,
      'promotional'=>(int)$promotionalCount,
      'promotions_active'=>(int)$promotionsActive,
      'promotions_total'=>(int)$promotionsTotal,
      'low_stock'=>(int)$lowStock,
      'out_stock'=>(int)$outStock,
      'sold_today'=>(int)$productsSoldToday,
      'sold_yesterday'=>(int)$productsSoldYesterday,
      'sold_trend'=>trend((int)$productsSoldToday,(int)$productsSoldYesterday)
    ],
    'loyalty'=>['earned'=>(int)$loyEarned,'redeemed'=>(int)$loyRedeemed,'today'=>(int)$loyToday,'yesterday'=>(int)$loyYesterday,'trend'=>trend((int)$loyToday,(int)$loyYesterday)],
    'top_products'=>$topProducts,
    'recent_orders'=>$recent,
    'actions'=>[
      'to_confirm'=>(int)$toConfirm,
      'to_prepare'=>(int)$toPrepare,
      'to_ship'=>(int)$toShip,
      'low_stock'=>['count'=>(int)$lowStock,'items'=>$lowStockProducts],
      'out_stock'=>['count'=>(int)$outStock,'items'=>$outStockProducts],
      'pending_reviews'=>(int)$pendingReviews,
      'pending_tickets'=>(int)$pendingTickets,
      'pending_contacts'=>(int)$pendingContacts,
      'recent_disabled'=>(int)$recentDisabled,
      'expiring_promos'=>(int)$expiringPromos,
      'active_flash'=>(int)$activeFlash
    ],
    'kpis'=>[
      'today_revenue'=>(int)$todayRev,
      'today_orders'=>(int)$todayOrders,
      'waiting_orders'=>(int)$waitingOrders,
      'new_customers'=>(int)$newCustomersToday,
      'products_sold'=>(int)$productsSoldToday,
      'avg_basket'=>(int)($avgToday?:0),
      'loyalty_today'=>(int)$loyToday
    ]
  ]);
} catch(Throwable $e){
  appLog('error','dashboard error',['e'=>$e->getMessage()]);
  jsonError('Erreur.',500);
}
