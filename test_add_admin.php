<?php
session_start();
require_once __DIR__ . '/config/db.php';
$_SESSION['user_role'] = 'Admin';
$_POST = [
    'action' => 'add_admin',
    'admin_name' => 'Test Admin',
    'dob' => '1990-01-01',
    'address' => '123 Test St',
    'email' => 'test@test.com',
    'phone_number' => '1234567890',
    'major_subject' => 'Math'
];
$_SERVER['REQUEST_METHOD'] = 'POST';

try {
    require 'action.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

var_dump($_SESSION);
?>
