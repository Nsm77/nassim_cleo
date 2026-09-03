<?php
require __DIR__ . '/../api/_bootstrap.php';
try {
  $pdo = db();
  echo "DB OK: " . $pdo->query("SELECT count(*) FROM users")->fetchColumn() . " users\n";
  echo "Tables: ";
  $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
  foreach($stmt->fetchAll() as $r) echo $r['name']." ";
  echo "\nHealth: ok\n";
} catch (Throwable $e) {
  echo "ERR: ".$e->getMessage()."\n";
  echo $e->getTraceAsString();
}
