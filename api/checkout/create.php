<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée.',405);
requireCsrf();
$user = requireAuth();

$input = getJsonInput();
if (empty($input)) $input = $_POST;

// attendre: items: [{id, qty}], address_id OU new_address, promo_code, payment_method, customer_note
$itemsInput = $input['items'] ?? $input['cart'] ?? [];
$addressId = isset($input['address_id']) ? (int)$input['address_id'] : null;
$newAddr = $input['new_address'] ?? $input['shipping_address'] ?? null;
$promoCode = isset($input['promo_code']) ? strtoupper(trim($input['promo_code'])) : null;
$payment = $input['payment_method'] ?? 'cash_on_delivery';
$note = sanitizeString($input['customer_note'] ?? $input['note'] ?? '', 500);

if (empty($itemsInput) || !is_array($itemsInput)) jsonError('Votre panier est vide.',422);
if (count($itemsInput) > 50) jsonError('Panier trop volumineux.',422);

// valider items: doit être id + qty
$normalized = [];
foreach($itemsInput as $it) {
  $pid = trim($it['id'] ?? $it['product_id'] ?? '');
  $qty = (int)($it['qty'] ?? $it['quantity'] ?? 1);
  if ($pid==='' || $qty<1 || $qty>9) jsonError('Quantité invalide pour un produit.',422);
  $normalized[] = ['id'=>$pid,'qty'=>$qty];
}
// dédupliquer
$merged=[];
foreach($normalized as $n) {
  if (isset($merged[$n['id']])) $merged[$n['id']] += $n['qty'];
  else $merged[$n['id']] = $n['qty'];
}
$normalized=[];
foreach($merged as $pid=>$qty) $normalized[]=['id'=>$pid,'qty'=>min(9,$qty)];

try {
  $pdo = db();
  $pdo->beginTransaction();

  // Vérifier produits et calculer totaux côté serveur
  $productRows=[];
  $subtotal=0;
  foreach($normalized as $n) {
    $stmt = $pdo->prepare("SELECT id, name, brand, price, image, stock, track_stock, stock_quantity, active FROM products WHERE id=? LIMIT 1");
    $stmt->execute([$n['id']]);
    $p = $stmt->fetch();
    if (!$p || !$p['active']) {
      $pdo->rollBack();
      jsonError('Produit introuvable : '.$n['id'],404);
    }
    if (!$p['stock']) {
      $pdo->rollBack();
      jsonError('Produit épuisé : '.$p['name'],409);
    }
    if ((int)$p['track_stock']===1 && (int)$p['stock_quantity'] < $n['qty']) {
      $pdo->rollBack();
      jsonError('Stock insuffisant pour '.$p['name'].' (disponible: '.$p['stock_quantity'].')',409);
    }
    $productRows[] = ['p'=>$p,'qty'=>$n['qty']];
    $subtotal += (int)$p['price'] * (int)$n['qty'];
  }

  // Promotion validation
  $promo = null;
  $discount = 0;
  $promoId = null;
  if ($promoCode) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code=? COLLATE NOCASE AND active=1 LIMIT 1");
    $stmt->execute([$promoCode]);
    $promo = $stmt->fetch();
    if (!$promo) { $pdo->rollBack(); jsonError('Code promotionnel invalide.',404); }
    $now = date('Y-m-d H:i:s');
    if (!empty($promo['start_date']) && $now < $promo['start_date']) { $pdo->rollBack(); jsonError('Code pas encore valide.',400); }
    if (!empty($promo['end_date']) && $now > $promo['end_date']) { $pdo->rollBack(); jsonError('Code expiré.',400); }
    if (!empty($promo['usage_limit'])) {
      $c = $pdo->prepare("SELECT COUNT(*) FROM promotion_usage WHERE promotion_id=?");
      $c->execute([$promo['id']]);
      if ((int)$c->fetchColumn() >= (int)$promo['usage_limit']) { $pdo->rollBack(); jsonError('Code limite atteinte.',400); }
    }
    if (!empty($promo['per_user_limit'])) {
      $c = $pdo->prepare("SELECT COUNT(*) FROM promotion_usage WHERE promotion_id=? AND user_id=?");
      $c->execute([$promo['id'],$user['id']]);
      if ((int)$c->fetchColumn() >= (int)$promo['per_user_limit']) { $pdo->rollBack(); jsonError('Vous avez déjà utilisé ce code.',400); }
    }
    if ((int)$promo['minimum_order']>0 && $subtotal < (int)$promo['minimum_order']) { $pdo->rollBack(); jsonError('Minimum commande non atteint pour ce code.',400); }

    if ($promo['type']==='percentage') {
        $discount = (int) round($subtotal * ((int)$promo['value']/100));
        if (!empty($promo['maximum_discount'])) $discount = min($discount,(int)$promo['maximum_discount']);
    } else {
        $discount = (int)$promo['value'];
    }
    $discount = min($discount,$subtotal);
    $promoId = $promo['id'];
  }

  // Loyalty redemption
  $loyaltyDiscount = 0;
  $loyaltyPointsUsed = 0;
  $loyaltyRewardId = null;
  $loyaltyReward = null;
  $useLoyalty = !empty($input['use_loyalty']) || !empty($input['redeem_loyalty']) || !empty($input['loyalty_reward_id']) || !empty($input['reward_id']) || !empty($input['loyalty_points']);
  $requestedRewardId = (int)($input['loyalty_reward_id'] ?? $input['reward_id'] ?? 0);
  if ($useLoyalty) {
    // determine reward
    if ($requestedRewardId) {
      $stmt = $pdo->prepare("SELECT * FROM loyalty_rewards WHERE id=? AND active=1 LIMIT 1");
      $stmt->execute([$requestedRewardId]);
      $loyaltyReward = $stmt->fetch();
      if (!$loyaltyReward) { $pdo->rollBack(); jsonError('Récompense fidélité introuvable.',404); }
    } else {
      // default 1000 pts => 10 DT
      $stmt = $pdo->prepare("SELECT * FROM loyalty_rewards WHERE points_cost=1000 AND discount_value=10000 AND active=1 LIMIT 1");
      $stmt->execute();
      $loyaltyReward = $stmt->fetch();
      if (!$loyaltyReward) {
        // fallback to settings
        $cost = (int)getSetting('loyalty_reward_threshold','1000');
        $val = (int)getSetting('loyalty_reward_value','10000');
        $loyaltyReward = ['id'=>null,'points_cost'=>$cost,'discount_value'=>$val,'code'=>'REWARD10'];
      }
    }
    $acc = getLoyaltyAccount((int)$user['id']);
    if ((int)$acc['balance'] < (int)$loyaltyReward['points_cost']) {
      $pdo->rollBack(); jsonError('Solde fidélité insuffisant. Vous avez '.(int)$acc['balance'].' points, besoin de '.(int)$loyaltyReward['points_cost'].'.',400);
    }
    // check per_user_limit / usage_limit if reward has id
    if (!empty($loyaltyReward['id'])) {
      if (!empty($loyaltyReward['usage_limit'])) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE type='redeemed' AND reference=?");
        $c->execute(['reward:'.$loyaltyReward['id']]);
        if ((int)$c->fetchColumn() >= (int)$loyaltyReward['usage_limit']) { $pdo->rollBack(); jsonError('Cette récompense a atteint sa limite.',400); }
      }
      if (!empty($loyaltyReward['per_user_limit'])) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM loyalty_transactions WHERE user_id=? AND type='redeemed' AND reference=?");
        $c->execute([$user['id'],'reward:'.$loyaltyReward['id']]);
        if ((int)$c->fetchColumn() >= (int)$loyaltyReward['per_user_limit']) { $pdo->rollBack(); jsonError('Vous avez déjà utilisé cette récompense.',400); }
      }
      $loyaltyRewardId = (int)$loyaltyReward['id'];
    }
    $loyaltyDiscount = (int)$loyaltyReward['discount_value'];
    $loyaltyPointsUsed = (int)$loyaltyReward['points_cost'];
    // cap discount to subtotal - promo discount
    $maxDiscount = max(0, $subtotal - $discount);
    if ($loyaltyDiscount > $maxDiscount) $loyaltyDiscount = $maxDiscount;
    if ($loyaltyDiscount <=0) { $loyaltyDiscount=0; $loyaltyPointsUsed=0; $loyaltyRewardId=null; }
  }

  // Shipping (after all discounts)
  $shipping = calculateShipping($subtotal - $discount - $loyaltyDiscount);
  $total = max(0, $subtotal - $discount - $loyaltyDiscount + $shipping);
  $loyaltyPointsEarned = calculateLoyaltyPoints(max(0, $subtotal - $discount - $loyaltyDiscount));

  // Adresse
  $shipData = null;
  if ($addressId) {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id=? AND user_id=? LIMIT 1");
    $stmt->execute([$addressId,$user['id']]);
    $shipData = $stmt->fetch();
    if (!$shipData) { $pdo->rollBack(); jsonError('Adresse introuvable.',404); }
  } elseif ($newAddr && is_array($newAddr)) {
    // créer adresse à la volée ou juste utiliser sans sauvegarder? On sauvegarde si demandé
    $first = sanitizeString($newAddr['first_name'] ?? '',80);
    $last  = sanitizeString($newAddr['last_name'] ?? '',80);
    $phone = sanitizeString($newAddr['phone'] ?? '',30);
    $addrLine = sanitizeString($newAddr['address'] ?? '',255);
    $city = sanitizeString($newAddr['city'] ?? '',80);
    $postal = sanitizeString($newAddr['postal_code'] ?? '',20);
    $addInfo= sanitizeString($newAddr['additional_information'] ?? '',255);
    if (mb_strlen($first)<2 || mb_strlen($last)<2 || mb_strlen($addrLine)<5 || mb_strlen($city)<2 || mb_strlen($postal)<3) {
        $pdo->rollBack(); jsonError('Adresse de livraison incomplète.',422);
    }
    // option sauvegarde ?
    $save = !empty($newAddr['save_address']);
    if ($save) {
        $lbl = sanitizeString($newAddr['label'] ?? 'Livraison',40);
        $isDef = 0;
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id=?");
        $cnt->execute([$user['id']]);
        if ((int)$cnt->fetchColumn()===0) $isDef=1;
        $pdo->prepare("INSERT INTO user_addresses(user_id,label,first_name,last_name,phone,address,city,postal_code,additional_information,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$user['id'],$lbl,$first,$last,$phone?:null,$addrLine,$city,$postal,$addInfo?:null,$isDef]);
        $nid = $pdo->lastInsertId();
        $shipData = $pdo->query("SELECT * FROM user_addresses WHERE id=$nid")->fetch();
    } else {
        $shipData = [
            'first_name'=>$first,'last_name'=>$last,'phone'=>$phone,'address'=>$addrLine,'city'=>$city,'postal_code'=>$postal,'additional_information'=>$addInfo, 'label'=>'Livraison'
        ];
    }
  } else {
    // essayer adresse par défaut
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id=? AND is_default=1 LIMIT 1");
    $stmt->execute([$user['id']]);
    $shipData = $stmt->fetch();
    if (!$shipData) {
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id=? ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$user['id']]);
        $shipData = $stmt->fetch();
    }
    if (!$shipData) { $pdo->rollBack(); jsonError('Veuillez renseigner une adresse de livraison.',422, ['address'=>'Adresse requise']); }
  }

  // Créer commande
  $orderNumber = generateOrderNumber($pdo);
  $now = now();
  $stmt = $pdo->prepare("INSERT INTO orders(order_number, user_id, status, subtotal, discount, shipping, total, payment_method, customer_note, promo_code, promo_discount, promotion_id, loyalty_discount, loyalty_points_used, loyalty_points_earned, loyalty_reward_id, shipping_address_json, shipping_first_name, shipping_last_name, shipping_phone, shipping_address, shipping_city, shipping_postal_code, shipping_additional, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $orderNumber, $user['id'], $subtotal, $discount, $shipping, $total, $payment, $note ?: null, $promoCode ?: null, $discount, $promoId,
    $loyaltyDiscount, $loyaltyPointsUsed, $loyaltyPointsEarned, $loyaltyRewardId,
    json_encode($shipData, JSON_UNESCAPED_UNICODE),
    $shipData['first_name'], $shipData['last_name'], $shipData['phone'] ?? null, $shipData['address'], $shipData['city'], $shipData['postal_code'], $shipData['additional_information'] ?? null,
    $now, $now
  ]);
  $orderId = (int)$pdo->lastInsertId();

  // Loyalty deduction inside same transaction (points redeemed now)
  if ($loyaltyPointsUsed > 0) {
    // deduct points: we cannot call loyaltyAddPoints that does its own balance calc with separate query outside transaction? It uses same PDO, so safe.
    // But we already validated balance, now deduct
    $accBefore = getLoyaltyAccount((int)$user['id']);
    $newBalance = (int)$accBefore['balance'] - $loyaltyPointsUsed;
    $pdo->prepare("UPDATE loyalty_accounts SET balance=?, lifetime_redeemed = lifetime_redeemed + ?, updated_at=? WHERE user_id=?")
        ->execute([$newBalance, $loyaltyPointsUsed, $now, $user['id']]);
    $pdo->prepare("INSERT INTO loyalty_transactions(user_id, order_id, type, points, balance_after, reference, created_by) VALUES (?, ?, 'redeemed', ?, ?, ?, ?)")
        ->execute([$user['id'], $orderId, -$loyaltyPointsUsed, $newBalance, $loyaltyRewardId ? 'reward:'.$loyaltyRewardId : 'reward:10DT', $user['id']]);
    // notification
    try { $pdo->prepare("INSERT INTO notifications(user_id, type, title, body, link) VALUES (?, 'loyalty', ?, ?, ?)")->execute([$user['id'], '-'.$loyaltyPointsUsed.' points utilisés', 'Réduction de '.($loyaltyDiscount/1000).' DT appliquée à '.$orderNumber, 'orders/detail.php?order_number='.$orderNumber]); } catch(Throwable $e){}
  }

  // Order items snapshots
  foreach($productRows as $pr) {
    $p=$pr['p']; $qty=$pr['qty'];
    $pdo->prepare("INSERT INTO order_items(order_id, product_id, product_name, brand, price, quantity, image) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$orderId, $p['id'], $p['name'], $p['brand'], $p['price'], $qty, $p['image']]);
    // stock décrément si track_stock
    if ((int)$p['track_stock']===1) {
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, updated_at=? WHERE id=?")->execute([$qty, $now, $p['id']]);
        // mettre à jour stock flag si épuisé?
        $chk = $pdo->prepare("SELECT stock_quantity FROM products WHERE id=?");
        $chk->execute([$p['id']]);
        $remain = (int)$chk->fetchColumn();
        if ($remain <= 0) {
            $pdo->prepare("UPDATE products SET stock=0 WHERE id=?")->execute([$p['id']]);
        }
    }
  }

  // Promotion usage
  if ($promoId) {
    $pdo->prepare("INSERT INTO promotion_usage(promotion_id, user_id, order_id) VALUES (?, ?, ?)")->execute([$promoId, $user['id'], $orderId]);
  }

  // Tracking initial
  $pdo->prepare("INSERT INTO order_tracking(order_id, status, note) VALUES (?, 'pending', ?)")->execute([$orderId, 'Commande reçue. En attente de confirmation.']);

  $pdo->commit();

  // Log activité
  appLog('info','order created',['order_id'=>$orderId,'user_id'=>$user['id'],'total'=>$total]);

  // Retourner commande
  $order = $pdo->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
  $items = $pdo->query("SELECT * FROM order_items WHERE order_id=$orderId")->fetchAll();
  jsonSuccess(['order'=>$order,'items'=>$items], 'Commande créée avec succès.', 201);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  // si jsonError déjà envoyé, ne pas double
  if (http_response_code() >= 400) { exit; }
  appLog('error','checkout error',['e'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
  global $config;
  if (!empty($config['app_debug'])) {
    jsonError('Erreur checkout: '.$e->getMessage(),500);
  }
  jsonError('Une erreur est survenue lors de la création de la commande.',500);
}
