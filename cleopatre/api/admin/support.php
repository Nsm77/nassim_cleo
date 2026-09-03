<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireSuperAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $status=trim($_GET['status'] ?? '');
  $page=max(1,(int)($_GET['page']??1));
  $per=min(50,max(1,(int)($_GET['per_page']??15)));
  $where="1=1"; $params=[];
  if($status) { $where.=" AND t.status=?"; $params[]=$status; }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM support_tickets t WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.email FROM support_tickets t JOIN users u ON u.id=t.user_id WHERE $where ORDER BY t.updated_at DESC LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  jsonSuccess(['tickets'=>$stmt->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/$per)]]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $ticketId=(int)($input['ticket_id'] ?? $input['id'] ?? 0);
  $message=sanitizeString($input['message'] ?? $input['reply'] ?? '',2000);
  $status=trim($input['status'] ?? '');
  $internal=!empty($input['is_internal']);
  if(!$ticketId) jsonError('ticket_id requis.',422);
  $stmt=$pdo->prepare("SELECT * FROM support_tickets WHERE id=? LIMIT 1");
  $stmt->execute([$ticketId]);
  $t=$stmt->fetch();
  if(!$t) jsonError('Ticket introuvable.',404);
  if($message){
    $pdo->prepare("INSERT INTO support_messages(ticket_id,user_id,message,is_internal) VALUES (?,?,?,?)")->execute([$ticketId,$admin['id'],$message,$internal?1:0]);
    if(!$internal){
      createNotification((int)$t['user_id'],'support','Réponse à votre ticket '.$t['ticket_number'],$message,"pages/support.html?ticket=".$t['ticket_number']);
    }
  }
  if($status && in_array($status,['open','waiting','resolved','closed'])){
    $pdo->prepare("UPDATE support_tickets SET status=?, updated_at=? WHERE id=?")->execute([$status,now(),$ticketId]);
  } else if($message && !$status){
    // auto set to waiting if admin replied
    $pdo->prepare("UPDATE support_tickets SET status='waiting', updated_at=? WHERE id=?")->execute([now(),$ticketId]);
  }
  adminLog((int)$admin['id'],'support_reply','support_ticket',(string)$ticketId,['status'=>$status]);
  jsonSuccess(null,'Mis à jour.');
}
jsonError('Méthode non autorisée.',405);
