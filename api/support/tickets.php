<?php
require __DIR__ . '/../_bootstrap.php';
$user=requireAuth();
$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->prepare("SELECT * FROM support_tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
  $stmt->execute([$user['id']]);
  jsonSuccess(['tickets'=>$stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $input=getJsonInput(); if(empty($input)) $input=$_POST;
  $subject=sanitizeString($input['subject'] ?? '',120);
  $message=sanitizeString($input['message'] ?? '',2000);
  $orderId=isset($input['order_id']) ? (int)$input['order_id'] : null;
  if(mb_strlen($subject)<5 || mb_strlen($message)<10) jsonError('Sujet et message requis (min 10 car).',422);
  $ticketNum='TKT-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
  $pdo->prepare("INSERT INTO support_tickets(ticket_number,user_id,order_id,subject) VALUES (?,?,?,?)")->execute([$ticketNum,$user['id'],$orderId?:null,$subject]);
  $tid=$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO support_messages(ticket_id,user_id,message) VALUES (?,?,?)")->execute([$tid,$user['id'],$message]);
  createNotification($user['id'],'support','Ticket créé — '.$ticketNum,'Nous vous répondrons sous 24h ouvrées.',"pages/support.html?ticket=".$ticketNum);
  // admin notification via log
  adminLog((int)$user['id'],'support_ticket_created','support_ticket',(string)$tid,['ticket_number'=>$ticketNum]);
  jsonSuccess(['ticket'=>['id'=>$tid,'ticket_number'=>$ticketNum]],'Ticket créé.',201);
}
jsonError('Méthode non autorisée.',405);
