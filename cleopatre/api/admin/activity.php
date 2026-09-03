<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$pdo=db();
$page=max(1,(int)($_GET['page'] ?? 1));
$per=min(50,max(1,(int)($_GET['per_page'] ?? 20)));
$cnt=$pdo->query("SELECT COUNT(*) FROM admin_activity_logs")->fetchColumn();
$total=(int)$cnt;
$offset=($page-1)*$per;
$stmt=$pdo->prepare("SELECT l.*, u.email as admin_email, u.first_name, u.last_name FROM admin_activity_logs l LEFT JOIN users u ON u.id=l.admin_id ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$per,$offset]);
jsonSuccess(['logs'=>$stmt->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
