<?php
require __DIR__ . '/../_bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$pdo=db();
$rows=$pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
foreach($rows as &$r){ $r['keywords']=$r['keywords']?json_decode($r['keywords'],true):[]; }
jsonSuccess(['categories'=>$rows]);
