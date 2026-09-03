<?php
require __DIR__ . '/../_bootstrap.php';
$actor = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->query("SELECT id, uuid, first_name, last_name, email, role, status, created_at, last_login_at FROM users WHERE role IN ('admin','super_admin','manager','staff') ORDER BY created_at DESC");
  jsonSuccess(['admins'=>$stmt->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $action=trim($input['action'] ?? 'create');
  if($action==='create'){
    $email=normalizeEmail(trim($input['email'] ?? ''));
    $first=sanitizeString($input['first_name'] ?? '',80);
    $last=sanitizeString($input['last_name'] ?? '',80);
    $role=trim($input['role'] ?? '');
    $pwd=$input['password'] ?? '';
    if(!validateEmail($email)) jsonError('E-mail invalide.',422);
    // STRICT two-level: only admin or super_admin
    if(!in_array($role,['admin','super_admin'])) jsonError('Rôle invalide — seul admin ou super_admin autorisé.',422);
    if($actor['role']!=='super_admin' && $role==='super_admin') jsonError('Seul Super Admin peut créer un Super Admin.',403);
    // Enforce single super_admin
    if($role==='super_admin'){
      $cnt=$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
      if((int)$cnt>=1) jsonError('Un Super Admin existe déjà — création bloquée (un seul autorisé).',409);
    }
    $msg=null; if(!validatePassword($pwd,$msg)) jsonError($msg,422);
    $chk=$pdo->prepare("SELECT 1 FROM users WHERE email=? COLLATE NOCASE");
    $chk->execute([$email]);
    if($chk->fetchColumn()) jsonError('E-mail déjà utilisé.',409);
    $hash=password_hash($pwd, PASSWORD_DEFAULT);
    $uuid=generateUuid();
    $pdo->prepare("INSERT INTO users(uuid, first_name, last_name, email, phone, password_hash, role, status) VALUES (?,?,?,?,?,?,?, 'active')")
      ->execute([$uuid,$first,$last,$email, trim($input['phone']??''), $hash,$role]);
    $id=$pdo->lastInsertId();
    adminLog((int)$actor['id'],'admin_create','user',(string)$id,['email'=>$email,'role'=>$role]);
    jsonSuccess(['id'=>$id],'Administrateur créé.',201);
  }
  if($action==='update_role'){
    $id=(int)($input['id'] ?? 0);
    $role=trim($input['role'] ?? '');
    if(!$id || !in_array($role,['admin','super_admin'])) jsonError('Paramètres invalides — seul admin/super_admin.',422);
    if($actor['role']!=='super_admin' && $role==='super_admin') jsonError('Non autorisé.',403);
    if($role==='super_admin'){
      $cnt=$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
      $target=$pdo->prepare("SELECT role FROM users WHERE id=?"); $target->execute([$id]); $existing=$target->fetchColumn();
      if($existing!=='super_admin' && (int)$cnt>=1) jsonError('Un Super Admin existe déjà — promotion bloquée.',409);
    }
    $pdo->prepare("UPDATE users SET role=?, updated_at=? WHERE id=?")->execute([$role,now(),$id]);
    adminLog((int)$actor['id'],'admin_role_change','user',(string)$id,['role'=>$role]);
    jsonSuccess(null,'Rôle mis à jour.');
  }
  if($action==='toggle_status'){
    $id=(int)($input['id'] ?? 0);
    $status=trim($input['status'] ?? '');
    if(!in_array($status,['active','disabled','suspended'])) jsonError('Statut invalide.',422);
    if($id===(int)$actor['id']) jsonError('Vous ne pouvez pas désactiver votre propre compte.',400);
    $pdo->prepare("UPDATE users SET status=?, updated_at=? WHERE id=?")->execute([$status,now(),$id]);
    adminLog((int)$actor['id'],'admin_status_change','user',(string)$id,['status'=>$status]);
    jsonSuccess(null,'Statut mis à jour.');
  }
  jsonError('Action inconnue.',422);
}
if($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=(int)($_GET['id'] ?? getJsonInput()['id'] ?? 0);
  if(!$id) jsonError('ID requis.',422);
  if($id===(int)$actor['id']) jsonError('Vous ne pouvez pas supprimer votre propre compte.',400);
  // prevent deleting last super_admin
  $cnt=$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND status='active'")->fetchColumn();
  $target=$pdo->prepare("SELECT role FROM users WHERE id=?"); $target->execute([$id]); $tr=$target->fetchColumn();
  if($tr==='super_admin' && (int)$cnt<=1) jsonError('Impossible de supprimer le dernier Super Admin.',400);
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
  adminLog((int)$actor['id'],'admin_delete','user',(string)$id);
  jsonSuccess(null,'Administrateur supprimé.');
}
jsonError('Méthode non autorisée.',405);
