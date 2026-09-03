<?php
require __DIR__ . '/../_bootstrap.php';
$user=requireAuth();
$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='GET'){
  $tid=(int)($_GET['ticket_id'] ?? 0);
  if(!$tid) jsonError('ticket_id requis.',422);
  $chk=$pdo->prepare("SELECT id FROM support_tickets WHERE id=? AND user_id=? LIMIT 1");
  $chk->execute([$tid,$user['id']]);
  if(!$chk->fetchColumn() && !isAdmin($user)) jsonError('Ticket introuvable.',404);
  $stmt=$pdo->prepare("SELECT m.*, u.first_name FROM support_messages m JOIN users u ON u.id=m.user_id WHERE m.ticket_id=? ORDER BY m.created_at ASC");
  $stmt->execute([$tid]);
  $msgs=$stmt->fetchAll();
  // filter internal if not admin
  if(!isAdmin($user)) $msgs=array_values(array_filter($msgs, fn($m)=>!(int)$m['is_internal']));
  jsonSuccess(['messages'=>$msgs]);
}
if ($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $tid=(int)($input['ticket_id'] ?? 0);
  $msg=sanitizeString($input['message'] ?? '',2000);
  if(!$tid || mb_strlen($msg)<3) jsonError('Message requis.',422);
  $chk=$pdo->prepare("SELECT * FROM support_tickets WHERE id=? LIMIT 1");
  $chk->execute([$tid]);
  $t=$chk->fetch();
  if(!$t) jsonError('Ticket introuvable.',404);
  if((int)$t['user_id']!== (int)$user['id'] && !isAdmin($user)) jsonError('Accès refusé.',403);
  $pdo->prepare("INSERT INTO support_messages(ticket_id,user_id,message) VALUES (?,?,?)")->execute([$tid,$user['id'],$msg]);
  if(isAdmin($user)){
    createNotification((int)$t['user_id'],'support','Réponse à votre ticket '.$t['ticket_number'],'Une réponse a été ajoutée à votre demande.',"pages/support.html?ticket=".$t['ticket_number']);
  }
  jsonSuccess(null,'Message envoyé.',201);
}
jsonError('Méthode non autorisée.',405);
