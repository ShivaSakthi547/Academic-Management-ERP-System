<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT id, class_name, grade, section FROM classes');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
