<?php
require __DIR__ . '/../_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non autorisée.', 405);
jsonSuccess(['csrf_token' => csrfToken()]);
