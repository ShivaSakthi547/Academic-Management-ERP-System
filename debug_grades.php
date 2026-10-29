<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query('SELECT grade, COUNT(*) as c FROM students GROUP BY grade');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SELECT grade FROM fee_structures');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
$stmt3 = $pdo->query('SELECT * FROM student_fees');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
