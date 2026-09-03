<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$user=requireAuth();
$pdo=db();
$page=max(1,(int)($_GET['page']??1));
$per=min(50,max(1,(int)($_GET['per_page']??20)));
$offset=($page-1)*$per;
$cnt=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
$cnt->execute([$user['id']]);
$total=(int)$cnt->fetchColumn();
$stmt=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$user['id'],$per,$offset]);
$rows=$stmt->fetchAll();
$unread=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$unread->execute([$user['id']]);
jsonSuccess(['notifications'=>$rows,'unread'=>(int)$unread->fetchColumn(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
