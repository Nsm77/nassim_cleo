<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
$input = getJsonInput();
if (empty($input)) $input = $_POST;
$code = strtoupper(trim($input['code'] ?? ''));
$subtotal = isset($input['subtotal']) ? (int)$input['subtotal'] : null;
if ($code==='') jsonError('Code promotionnel requis.',422);

try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? COLLATE NOCASE AND active=1 LIMIT 1");
  $stmt->execute([$code]);
  $promo = $stmt->fetch();
  if (!$promo) jsonError('Code invalide ou expiré.',404);
  // vérifier dates
  $now = date('Y-m-d H:i:s');
  if (!empty($promo['start_date']) && $now < $promo['start_date']) jsonError('Ce code n’est pas encore valide.',400);
  if (!empty($promo['end_date']) && $now > $promo['end_date']) jsonError('Ce code a expiré.',400);
  if (!empty($promo['usage_limit'])) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM promotion_usage WHERE promotion_id=?");
    $c->execute([$promo['id']]);
    if ((int)$c->fetchColumn() >= (int)$promo['usage_limit']) jsonError('Ce code a atteint sa limite d’utilisation.',400);
  }
  $user = currentUser();
  if ($user && !empty($promo['per_user_limit'])) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM promotion_usage WHERE promotion_id=? AND user_id=?");
    $c->execute([$promo['id'], $user['id']]);
    if ((int)$c->fetchColumn() >= (int)$promo['per_user_limit']) jsonError('Vous avez déjà utilisé ce code.',400);
  }
  if ($subtotal !== null && $promo['minimum_order'] > 0 && $subtotal < (int)$promo['minimum_order']) {
    $need = (int)$promo['minimum_order'] - $subtotal;
    jsonError('Commande minimum de '. number_format($promo['minimum_order']/1000,3,',',' ').' DT requise.',400);
  }
  // calcul discount preview
  $discount = 0;
  if ($subtotal !== null) {
    if ($promo['type']==='percentage') $discount = (int) round($subtotal * ((int)$promo['value']/100));
    else $discount = (int)$promo['value'];
    if (!empty($promo['maximum_discount'])) $discount = min($discount, (int)$promo['maximum_discount']);
    $discount = min($discount, $subtotal);
  }
  jsonSuccess(['promotion'=>[
    'id'=>$promo['id'],
    'code'=>$promo['code'],
    'type'=>$promo['type'],
    'value'=>(int)$promo['value'],
    'discount_preview'=>$discount
  ]], 'Code valide.');
} catch (Throwable $e) {
  if (str_contains($e->getMessage(),'Code invalide')) throw $e;
  appLog('error','promo validate error',['e'=>$e->getMessage()]);
  jsonError('Erreur.',500);
}
