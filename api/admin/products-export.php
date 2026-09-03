<?php
require __DIR__ . '/../_bootstrap.php';
$admin=requireAdmin();
$pdo=db();
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$format=$_GET['format'] ?? 'json';
$products=$pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll();
if($format==='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="cleopatre-products-'.date('Ymd').'.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['id','name','brand','brand_slug','cat','sub','form','tint','price','old_price','size','stock','featured','bestseller','is_new','active','track_stock','stock_quantity','rating','reviews']);
  foreach($products as $p){
    fputcsv($out,[$p['id'],$p['name'],$p['brand'],$p['brand_slug'],$p['cat'],$p['sub'],$p['form'],$p['tint'],$p['price'],$p['old_price'],$p['size'],$p['stock'],$p['featured'],$p['bestseller'],$p['is_new'],$p['active'],$p['track_stock'],$p['stock_quantity'],$p['rating'],$p['reviews']]);
  }
  fclose($out);
  exit;
}
jsonSuccess(['products'=>$products,'count'=>count($products)]);
