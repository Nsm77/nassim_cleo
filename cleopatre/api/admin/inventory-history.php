<?php
require __DIR__ . '/../_bootstrap.php';
$admin=requireAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$productId=trim($_GET['product_id'] ?? '');
$page=max(1,(int)($_GET['page'] ?? 1));
$per=min(100,max(1,(int)($_GET['per_page'] ?? 20)));
$where="1=1"; $params=[];
if($productId){ $where.=" AND m.product_id=?"; $params[]=$productId; }
$cnt=$pdo->prepare("SELECT COUNT(*) FROM inventory_movements m WHERE $where");
$cnt->execute($params);
$total=(int)$cnt->fetchColumn();
$offset=($page-1)*$per;
$stmt=$pdo->prepare("SELECT m.*, p.name as product_name, p.brand, u.email as admin_email FROM inventory_movements m LEFT JOIN products p ON p.id=m.product_id LEFT JOIN users u ON u.id=m.admin_id WHERE $where ORDER BY m.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params,[$per,$offset]));
jsonSuccess(['movements'=>$stmt->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/max(1,$per))]]);
