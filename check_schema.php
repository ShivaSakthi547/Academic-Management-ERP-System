<?php
require 'config/db.php';
$res = $pdo->query('SHOW COLUMNS FROM attendance');
print_r($res->fetchAll(PDO::FETCH_ASSOC));
$res = $pdo->query('SHOW COLUMNS FROM fee_payments');
print_r($res->fetchAll(PDO::FETCH_ASSOC));
