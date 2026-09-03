<?php
require __DIR__ . '/../_bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET') jsonError('Méthode non autorisée.',405);
$pdo=db();
$rows=$pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
foreach($rows as &$r){ $r['values']=$r['values_json']?json_decode($r['values_json'],true):[]; $r['story']=$r['story']?json_decode($r['story'],true):[]; }
jsonSuccess(['brands'=>$rows]);
