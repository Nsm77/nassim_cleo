<?php
require __DIR__ . '/../_bootstrap.php';
$admin = requireAdmin();
$pdo = db();

// Helpers
function productValidate(array $data, bool $isCreate=false): array {
  $errors=[];
  if($isCreate){
    if(empty($data['id'])) $errors['id']='ID requis (slug unique)';
    if(empty($data['name'])) $errors['name']='Nom requis';
    if(empty($data['brand'])) $errors['brand']='Marque requise';
    if(empty($data['cat'])) $errors['cat']='Catégorie requise';
    if(!isset($data['price']) || (int)$data['price']<0) $errors['price']='Prix invalide';
  } else {
    if(isset($data['price']) && (int)$data['price']<0) $errors['price']='Prix invalide';
    if(isset($data['old_price']) && $data['old_price']!=='' && $data['old_price']!==null && (int)$data['old_price']<0) $errors['old_price']='Ancien prix invalide';
  }
  return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $q=trim($_GET['q'] ?? '');
  $cat=trim($_GET['cat'] ?? $_GET['category'] ?? '');
  $sub=trim($_GET['sub'] ?? $_GET['subcategory'] ?? '');
  $brand=trim($_GET['brand'] ?? '');
  $min=isset($_GET['min']) && $_GET['min']!=='' ? (int)$_GET['min'] : null;
  $max=isset($_GET['max']) && $_GET['max']!=='' ? (int)$_GET['max'] : null;
  // also support price_min/price_max in DT float? convert
  if(isset($_GET['price_min']) && $_GET['price_min']!=='' ) $min=(int)round(floatval($_GET['price_min'])*1000);
  if(isset($_GET['price_max']) && $_GET['price_max']!=='' ) $max=(int)round(floatval($_GET['price_max'])*1000);
  $stockFilter=trim($_GET['stock'] ?? '');
  $status=trim($_GET['status'] ?? '');
  $featured=isset($_GET['featured']) && $_GET['featured']!=='' ? $_GET['featured'] : null;
  $bestseller=isset($_GET['bestseller']) && $_GET['bestseller']!=='' ? $_GET['bestseller'] : null;
  $isNew=isset($_GET['is_new']) && $_GET['is_new']!=='' ? $_GET['is_new'] : null;
  $promo=isset($_GET['promo']) && $_GET['promo']!=='' ? $_GET['promo'] : null;
  $sort=trim($_GET['sort'] ?? 'name');
  $page=max(1,(int)($_GET['page'] ?? 1));
  $per=min(100,max(1,(int)($_GET['per_page'] ?? 20)));

  $where="1=1";
  $params=[];
  if($q!==''){
    $where.=" AND (p.id LIKE ? OR p.name LIKE ? OR p.brand LIKE ? OR p.brand_slug LIKE ? OR p.cat LIKE ? OR p.sub LIKE ?)";
    $like="%$q%";
    $params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;
  }
  if($cat!=='' ){ $where.=" AND p.cat=?"; $params[]=$cat; }
  if($sub!=='' ){ $where.=" AND p.sub=?"; $params[]=$sub; }
  if($brand!=='' ){
    $where.=" AND (p.brand_slug=? OR lower(p.brand)=lower(?))";
    $params[]=$brand;$params[]=$brand;
  }
  if($min!==null){ $where.=" AND p.price >= ?"; $params[]=$min; }
  if($max!==null){ $where.=" AND p.price <= ?"; $params[]=$max; }
  if($stockFilter!=='' ){
    if($stockFilter==='low') $where.=" AND p.track_stock=1 AND p.stock_quantity>0 AND p.stock_quantity<=5";
    elseif($stockFilter==='out') $where.=" AND (p.stock=0 OR (p.track_stock=1 AND p.stock_quantity<=0))";
    elseif($stockFilter==='in') $where.=" AND p.stock=1 AND (p.track_stock=0 OR p.stock_quantity>5)";
  }
  if($status!=='' ){
    if($status==='active') $where.=" AND p.active=1";
    elseif($status==='inactive') $where.=" AND p.active=0";
  }
  if($featured!==null && $featured!==''){
    $where.=" AND p.featured=?"; $params[]=$featured ? 1:0;
  }
  if($bestseller!==null && $bestseller!==''){
    if($bestseller) $where.=" AND p.bestseller IS NOT NULL";
    else $where.=" AND p.bestseller IS NULL";
  }
  if($isNew!==null && $isNew!==''){
    $where.=" AND p.is_new=?"; $params[]=$isNew?1:0;
  }
  if($promo!==null && $promo!==''){
    if($promo) $where.=" AND (p.old_price IS NOT NULL AND p.old_price>p.price OR p.promo_active=1)";
    else $where.=" AND (p.old_price IS NULL OR p.old_price<=p.price) AND p.promo_active=0";
  }

  $order="p.name ASC";
  switch($sort){
    case 'price-asc': $order="p.price ASC"; break;
    case 'price-desc': $order="p.price DESC"; break;
    case 'stock': $order="p.stock_quantity ASC"; break;
    case 'newest': $order="p.created_at DESC"; break;
    case 'updated': $order="p.updated_at DESC"; break;
    case 'featured': $order="p.featured DESC, p.name ASC"; break;
    case 'bestseller': $order="p.bestseller IS NULL, p.bestseller ASC"; break;
    case 'rating': $order="p.rating DESC"; break;
  }

  $cnt=$pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
  $cnt->execute($params);
  $total=(int)$cnt->fetchColumn();
  $offset=($page-1)*$per;
  $stmt=$pdo->prepare("SELECT p.* FROM products p WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
  $stmt->execute(array_merge($params,[$per,$offset]));
  $products=$stmt->fetchAll();
  // facets for filters (counts)
  $facets=[];
  try{
    $facets['categories']=$pdo->query("SELECT cat as slug, COUNT(*) as c FROM products GROUP BY cat")->fetchAll();
    $facets['brands']=$pdo->query("SELECT brand_slug as slug, brand as name, COUNT(*) as c FROM products GROUP BY brand_slug, brand")->fetchAll();
  }catch(Throwable $e){}
  jsonSuccess(['products'=>$products,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>ceil($total/max(1,$per))],'facets'=>$facets]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
  requireCsrf();
  $input=getJsonInput();
  if(empty($input)) $input=$_POST;
  $action=trim($input['action'] ?? '');

  // BULK ACTIONS
  if($action==='bulk'){
    $ids=$input['ids'] ?? [];
    $bulkAction=trim($input['bulk_action'] ?? '');
    if(!is_array($ids) || !count($ids)) jsonError('Sélection vide.',422);
    $allowed=['activate','deactivate','feature','unfeature','bestseller','unbestseller','set_category','set_brand','apply_discount','remove_discount','set_stock','delete'];
    if(!in_array($bulkAction,$allowed)) jsonError('Action bulk invalide.',422);
    $affected=0;
    $pdo->beginTransaction();
    try{
      foreach($ids as $pid){
        $pid=trim($pid);
        if(!$pid) continue;
        $exists=$pdo->prepare("SELECT id FROM products WHERE id=?");
        $exists->execute([$pid]);
        if(!$exists->fetchColumn()) continue;
        switch($bulkAction){
          case 'activate': $pdo->prepare("UPDATE products SET active=1, updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'deactivate': $pdo->prepare("UPDATE products SET active=0, updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'feature': $pdo->prepare("UPDATE products SET featured=1, updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'unfeature': $pdo->prepare("UPDATE products SET featured=0, updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'bestseller': $pdo->prepare("UPDATE products SET bestseller=COALESCE(bestseller, 99), updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'unbestseller': $pdo->prepare("UPDATE products SET bestseller=NULL, updated_at=? WHERE id=?")->execute([now(),$pid]); $affected++; break;
          case 'set_category':
            $cat=trim($input['category'] ?? '');
            $sub=trim($input['subcategory'] ?? '');
            if($cat) { $pdo->prepare("UPDATE products SET cat=?, sub=?, updated_at=? WHERE id=?")->execute([$cat, $sub?:null, now(),$pid]); $affected++; }
            break;
          case 'set_brand':
            $brand=trim($input['brand'] ?? '');
            $brandSlug=null;
            if($brand){
              $st=$pdo->prepare("SELECT slug FROM brands WHERE lower(name)=lower(?) LIMIT 1");
              $st->execute([$brand]);
              $brandSlug=$st->fetchColumn() ?: $brand;
              $pdo->prepare("UPDATE products SET brand=?, brand_slug=?, updated_at=? WHERE id=?")->execute([$brand,$brandSlug,now(),$pid]); $affected++;
            }
            break;
          case 'apply_discount':
            $dtype=trim($input['discount_type'] ?? 'percentage');
            $dval=(int)($input['discount_value'] ?? 0);
            if($dval>0){
              // store promo on product: set old_price if not set
              $p=$pdo->prepare("SELECT price, old_price FROM products WHERE id=?"); $p->execute([$pid]); $row=$p->fetch();
              if($row){
                $newPrice=$row['price'];
                if($dtype==='percentage'){ $newPrice=(int)round($row['price']*(1 - $dval/100)); }
                else { $newPrice=max(0, $row['price'] - $dval); }
                // Keep old_price = original if not set
                $oldPrice=$row['old_price'] ?: $row['price'];
                $pdo->prepare("UPDATE products SET old_price=?, price=?, promo_active=1, promo_discount_type=?, promo_discount_value=?, updated_at=? WHERE id=?")->execute([$oldPrice,$newPrice,$dtype,$dval,now(),$pid]);
                $affected++;
              }
            }
            break;
          case 'remove_discount':
            $p=$pdo->prepare("SELECT old_price, price FROM products WHERE id=?"); $p->execute([$pid]); $row=$p->fetch();
            if($row && $row['old_price']){
              $pdo->prepare("UPDATE products SET price=old_price, old_price=NULL, promo_active=0, updated_at=? WHERE id=?")->execute([now(),$pid]);
            } else {
              $pdo->prepare("UPDATE products SET promo_active=0, promo_discount_type=NULL, promo_discount_value=NULL, updated_at=? WHERE id=?")->execute([now(),$pid]);
            }
            $affected++;
            break;
          case 'set_stock':
            $qty=(int)($input['stock_quantity'] ?? 0);
            $p=$pdo->prepare("SELECT stock_quantity FROM products WHERE id=?"); $p->execute([$pid]); $old=(int)$p->fetchColumn();
            $diff=$qty-$old;
            $pdo->prepare("UPDATE products SET stock_quantity=?, stock=?, track_stock=1, updated_at=? WHERE id=?")->execute([$qty, $qty>0?1:0, now(),$pid]);
            if($diff!==0) $pdo->prepare("INSERT INTO inventory_movements(product_id, change_qty, previous_qty, new_qty, reason, admin_id) VALUES (?,?,?,?,?,?)")->execute([$pid,$diff,$old,$qty,'bulk_adjust',$admin['id']]);
            $affected++;
            break;
          case 'delete':
            // safe delete only if no order_items referencing
            $chk=$pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
            $chk->execute([$pid]);
            if((int)$chk->fetchColumn()>0) continue 2;
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
            $affected++;
            break;
        }
      }
      $pdo->commit();
    } catch(Throwable $e){ $pdo->rollBack(); jsonError('Erreur bulk: '.$e->getMessage(),500); }
    adminLog((int)$admin['id'],'product_bulk','product',implode(',',$ids),['action'=>$bulkAction,'count'=>$affected]);
    jsonSuccess(['affected'=>$affected],'Action bulk traitée.');
  }

  // DUPLICATE
  if($action==='duplicate'){
    $id=trim($input['id'] ?? '');
    if(!$id) jsonError('ID requis.',422);
    $stmt=$pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $p=$stmt->fetch();
    if(!$p) jsonError('Produit introuvable.',404);
    $newId=$p['id'].'-copie';
    $suffix=1;
    while(true){
      $chk=$pdo->prepare("SELECT 1 FROM products WHERE id=?");
      $chk->execute([$newId]);
      if(!$chk->fetch()) break;
      $newId=$p['id'].'-copie-'.$suffix;
      $suffix++;
    }
    $stmt=$pdo->prepare("INSERT INTO products(id, brand, brand_slug, name, cat, sub, form, tint, price, old_price, size, concerns, rating, reviews, stock, featured, bestseller, image, image_alt, image_thumb, short, description, ingredients, benefits, usage_text, active, track_stock, stock_quantity, is_new) SELECT ?, brand, brand_slug, name || ' (copie)', cat, sub, form, tint, price, old_price, size, concerns, 0, 0, stock, 0, NULL, image, image_alt, image_thumb, short, description, ingredients, benefits, usage_text, 0, track_stock, stock_quantity, is_new FROM products WHERE id=?");
    $stmt->execute([$newId,$id]);
    adminLog((int)$admin['id'],'product_duplicate','product',$newId,['from'=>$id]);
    $row=$pdo->query("SELECT * FROM products WHERE id=".$pdo->quote($newId))->fetch();
    jsonSuccess(['product'=>$row],'Produit dupliqué.');
  }

  // CREATE
  if($action==='create'){
    $id=trim($input['id'] ?? '');
    if(!$id) {
      // auto slug from name
      $name=trim($input['name'] ?? '');
      $id=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-', $name));
      $id=trim($id,'-');
      if(!$id) $id='prod-'.bin2hex(random_bytes(3));
    }
    $errors=productValidate(array_merge($input,['id'=>$id]), true);
    if($errors) jsonError('Validation échouée.',422,$errors);
    $chk=$pdo->prepare("SELECT 1 FROM products WHERE id=?");
    $chk->execute([$id]);
    if($chk->fetch()) jsonError('ID déjà existant.',409);
    $brand=trim($input['brand'] ?? '');
    $brandSlug=null;
    if($brand){
      $st=$pdo->prepare("SELECT slug FROM brands WHERE lower(name)=lower(?) LIMIT 1");
      $st->execute([$brand]);
      $brandSlug=$st->fetchColumn() ?: null;
      if(!$brandSlug) {
        // create slug naive
        $brandSlug=mb_strtolower(preg_replace('/[^a-z0-9]+/i','-',$brand));
      }
    }
    $stmt=$pdo->prepare("INSERT INTO products(id, brand, brand_slug, name, cat, sub, form, tint, price, old_price, size, concerns, rating, reviews, stock, featured, bestseller, image, image_alt, image_thumb, short, description, ingredients, benefits, usage_text, active, track_stock, stock_quantity, is_new, promo_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $id,
      $brand,
      $brandSlug,
      trim($input['name']),
      trim($input['cat'] ?? 'visage'),
      trim($input['sub'] ?? ''),
      trim($input['form'] ?? ''),
      trim($input['tint'] ?? '#ECE5D8'),
      (int)($input['price'] ?? 0),
      isset($input['old_price']) && $input['old_price']!=='' ? (int)$input['old_price'] : null,
      trim($input['size'] ?? ''),
      json_encode($input['concerns'] ?? [], JSON_UNESCAPED_UNICODE),
      floatval($input['rating'] ?? 0),
      (int)($input['reviews'] ?? 0),
      isset($input['stock']) ? ($input['stock']?1:0) : 1,
      isset($input['featured']) ? ($input['featured']?1:0):0,
      isset($input['bestseller']) && $input['bestseller']!=='' ? (int)$input['bestseller'] : null,
      trim($input['image'] ?? ''),
      trim($input['image_alt'] ?? ''),
      trim($input['image_thumb'] ?? ''),
      trim($input['short'] ?? ''),
      trim($input['description'] ?? ''),
      trim($input['ingredients'] ?? ''),
      json_encode($input['benefits'] ?? [], JSON_UNESCAPED_UNICODE),
      trim($input['usage_text'] ?? $input['usage'] ?? ''),
      isset($input['active']) ? ($input['active']?1:0):1,
      isset($input['track_stock']) ? ($input['track_stock']?1:0):0,
      (int)($input['stock_quantity'] ?? 0),
      isset($input['is_new']) ? ($input['is_new']?1:0):0,
      0
    ]);
    adminLog((int)$admin['id'],'product_create','product',$id,['name'=>$input['name']]);
    $row=$pdo->query("SELECT * FROM products WHERE id=".$pdo->quote($id))->fetch();
    jsonSuccess(['product'=>$row],'Produit créé.',201);
  }

  // UPDATE (legacy compat + full)
  $id=trim($input['id'] ?? '');
  if(!$id) jsonError('ID produit requis.',400);
  $stmt=$pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $p=$stmt->fetch();
  if(!$p) jsonError('Produit introuvable.',404);

  $fields=[];
  $params=[];
  $updatable=['active','featured','track_stock','stock_quantity','stock','price','old_price','name','brand','brand_slug','cat','sub','form','tint','size','concerns','rating','reviews','image','image_alt','image_thumb','short','description','ingredients','benefits','usage_text','bestseller','is_new','promo_active','promo_discount_type','promo_discount_value','promo_start','promo_end'];
  // special handling for concerns/benefits json
  $map=['usage'=>'usage_text'];
  foreach($map as $k=>$v){ if(isset($input[$k]) && !isset($input[$v])) $input[$v]=$input[$k]; }

  $oldQty=(int)$p['stock_quantity'];
  $oldPrice=(int)$p['price'];
  $oldStock=(int)$p['stock'];

  foreach($updatable as $col){
    if(array_key_exists($col,$input)){
      $val=$input[$col];
      // type handling
      if(in_array($col,['active','featured','track_stock','stock','is_new','promo_active'])) $val=$val?1:0;
      elseif(in_array($col,['stock_quantity','price','old_price','reviews'])) {
        if($val==='' || $val===null) $val=null;
        else $val=(int)$val;
        if($col==='price' && $val!==null && $val<0) jsonError('Prix invalide.',422);
      }
      elseif($col==='bestseller'){
        if($val==='' || $val===null) $val=null;
        else $val=(int)$val;
      }
      elseif($col==='rating'){ $val=floatval($val); }
      elseif(in_array($col,['concerns','benefits'])){
        if(is_array($val)) $val=json_encode($val, JSON_UNESCAPED_UNICODE);
        else $val=json_encode([], JSON_UNESCAPED_UNICODE);
      }
      elseif($col==='brand' && $val){
        // also resolve brand_slug if not provided
        if(!isset($input['brand_slug'])){
          $st=$pdo->prepare("SELECT slug FROM brands WHERE lower(name)=lower(?) LIMIT 1");
          $st->execute([$val]);
          $slug=$st->fetchColumn();
          if($slug) { $fields[]="brand_slug=?"; $params[]=$slug; }
        }
      }
      $fields[]="$col=?";
      $params[]=$val;
    }
  }
  // brand_slug explicit
  if(isset($input['brand_slug']) && !in_array('brand_slug=?',$fields)){
    $fields[]="brand_slug=?"; $params[]=$input['brand_slug'] ?: null;
  }
  if(empty($fields)) jsonError('Aucun champ à mettre à jour.',422);
  $fields[]="updated_at=?";
  $params[]=now();
  $params[]=$id;
  $sql="UPDATE products SET ".implode(',',$fields)." WHERE id=?";
  $pdo->prepare($sql)->execute($params);
  // inventory movement if qty changed
  if(isset($input['stock_quantity'])){
    $newQty=(int)$input['stock_quantity'];
    $diff=$newQty - $oldQty;
    if($diff!==0){
      $pdo->prepare("INSERT INTO inventory_movements(product_id, change_qty, previous_qty, new_qty, reason, admin_id) VALUES (?, ?, ?, ?, ?, ?)")->execute([$id,$diff,$oldQty,$newQty,'admin_adjust',(int)$admin['id']]);
    }
  }
  // audit
  $meta=['fields'=>array_keys($input)];
  if(isset($input['price']) && (int)$input['price']!==$oldPrice) $meta['price_change']=['from'=>$oldPrice,'to'=>(int)$input['price']];
  if(isset($input['stock_quantity']) && (int)$input['stock_quantity']!==$oldQty) $meta['stock_change']=['from'=>$oldQty,'to'=>(int)$input['stock_quantity']];
  adminLog((int)$admin['id'],'product_update','product',$id,$meta);
  $updated=$pdo->prepare("SELECT * FROM products WHERE id=?"); $updated->execute([$id]); $row=$updated->fetch();
  jsonSuccess(['product'=>$row],'Produit mis à jour.');
}

if ($_SERVER['REQUEST_METHOD']==='DELETE'){
  requireCsrf();
  $id=trim($_GET['id'] ?? '');
  if(!$id){
    $j=getJsonInput();
    $id=trim($j['id'] ?? '');
  }
  if(!$id) jsonError('ID requis.',422);
  $chk=$pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
  $chk->execute([$id]);
  if((int)$chk->fetchColumn()>0) jsonError('Suppression impossible : produit lié à des commandes. Désactivez-le plutôt.',409);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  adminLog((int)$admin['id'],'product_delete','product',$id);
  jsonSuccess(null,'Produit supprimé.');
}

jsonError('Méthode non autorisée.',405);
