<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
rateLimitOrFail('contact', 5, 3600, 'Trop de messages. Réessayez plus tard.');
$input = getJsonInput();
if (empty($input)) $input = $_POST;
$name = sanitizeString($input['name'] ?? '', 120);
$email = normalizeEmail(trim($input['email'] ?? ''));
$phone = sanitizeString($input['phone'] ?? '', 30);
$subject = sanitizeString($input['subject'] ?? '', 120);
$message = sanitizeString($input['message'] ?? '', 2000);
$errors=[];
if (mb_strlen($name)<2) $errors['name']='Nom requis.';
if (!validateEmail($email)) $errors['email']='E-mail invalide.';
if (mb_strlen($message)<10) $errors['message']='Message trop court (10 caractères minimum).';
if ($phone && !validatePhone($phone)) $errors['phone']='Téléphone invalide.';
if ($errors) jsonError('Veuillez corriger.',422,$errors);
try {
  $pdo = db();
  $pdo->prepare("INSERT INTO contact_messages(name,email,phone,subject,message) VALUES (?,?,?,?,?)")
      ->execute([$name,$email,$phone?:null,$subject?:null,$message]);
  appLog('info','contact message',['email'=>maskEmail($email)]);
  jsonSuccess(null,'Message bien reçu. Une pharmacienne vous répond sous 24h ouvrées.');
} catch(Throwable $e){
  appLog('error','contact error',['e'=>$e->getMessage()]);
  jsonError('Erreur.',500);
}
