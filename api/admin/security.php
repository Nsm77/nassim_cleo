<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$failedLogins=$pdo->query("SELECT bucket, created_at FROM rate_limits WHERE bucket LIKE 'login:%' ORDER BY created_at DESC LIMIT 20")->fetchAll();
$recentAdminLogs=$pdo->query("SELECT l.*, u.email FROM admin_activity_logs l LEFT JOIN users u ON u.id=l.admin_id ORDER BY l.created_at DESC LIMIT 20")->fetchAll();
$adminSessions=$pdo->query("SELECT id, first_name, last_name, email, last_login_at, status FROM users WHERE role IN ('admin','super_admin','manager','staff') ORDER BY last_login_at DESC")->fetchAll();
jsonSuccess(['failed_logins'=>$failedLogins,'recent_activity'=>$recentAdminLogs,'admin_sessions'=>$adminSessions]);
