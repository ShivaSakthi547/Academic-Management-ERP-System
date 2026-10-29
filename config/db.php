<?php
// config/db.php

$host = 'localhost';
$db   = 'academic_system';
$user = 'root';
$pass = ''; // default XAMPP MySQL password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connect to MySQL server first, to ensure database exists or create it
    $pdo_init = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Now connect to the specific database
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Auto-create tables if they do not exist (including is_active for users)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `username` VARCHAR(100) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `role` VARCHAR(50) NOT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if is_active column exists, add it if not (for migration/upgrade safety)
    $q = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'is_active'");
    if (!$q->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `student_id` VARCHAR(100) NOT NULL UNIQUE,
      `student_name` VARCHAR(255) NOT NULL,
      `date_of_birth` DATE NOT NULL,
      `gender` VARCHAR(20) NOT NULL,
      `email` VARCHAR(255) NOT NULL,
      `mobile` VARCHAR(20) NOT NULL,
      `address` TEXT NOT NULL,
      `grade` VARCHAR(50) NOT NULL,
      `role` VARCHAR(50) NOT NULL,
      `photo` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `deleted_at` DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Auto-create admins table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `admin_id` VARCHAR(100) NOT NULL UNIQUE,
      `admin_name` VARCHAR(255) NOT NULL,
      `dob` DATE NOT NULL,
      `address` TEXT NOT NULL,
      `photo` VARCHAR(255) NOT NULL DEFAULT 'uploads/default_avatar.png',
      `status` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if photo column exists in admins, add it if not
    $q_admin = $pdo->query("SHOW COLUMNS FROM `admins` LIKE 'photo'");
    if (!$q_admin->fetch()) {
        $pdo->exec("ALTER TABLE `admins` ADD `photo` VARCHAR(255) NOT NULL DEFAULT 'uploads/default_avatar.png' AFTER `address`");
    }

    // Auto-create staff table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `staff_id` VARCHAR(100) NOT NULL UNIQUE,
      `staff_name` VARCHAR(255) NOT NULL,
      `dob` DATE NOT NULL,
      `address` TEXT NOT NULL,
      `photo` VARCHAR(255) NOT NULL DEFAULT 'uploads/default_avatar.png',
      `status` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if photo column exists in staff, add it if not
    $q_staff = $pdo->query("SHOW COLUMNS FROM `staff` LIKE 'photo'");
    if (!$q_staff->fetch()) {
        $pdo->exec("ALTER TABLE `staff` ADD `photo` VARCHAR(255) NOT NULL DEFAULT 'uploads/default_avatar.png' AFTER `address`");
    }

    // Auto-create attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `student_id` INT NOT NULL,
      `class_id` INT NOT NULL,
      `attendance_date` DATE NOT NULL,
      `status` ENUM('Present', 'Absent') NOT NULL,
      `marked_by` INT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `student_date_unique` (`student_id`, `attendance_date`),
      FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`marked_by`) REFERENCES `staff`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Auto-create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(255) NOT NULL,
      `sender_role` VARCHAR(50) NOT NULL,
      `sender_id` INT NOT NULL,
      `message` TEXT NOT NULL,
      `target_type` VARCHAR(50) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `expiry_date` DATETIME NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Auto-create notification_recipients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notification_recipients` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `notification_id` INT NOT NULL,
      `receiver_role` VARCHAR(50) NOT NULL,
      `receiver_id` INT NOT NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `read_at` DATETIME NULL,
      FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Preload demo users if database is newly initialized
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `username` IN ('admin', 'user')");
    if ($stmt->fetchColumn() == 0) {
        $stmt_insert = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `role`, `is_active`) VALUES (?, ?, ?, 1)");
        // admin / 01-01-2001
        $stmt_insert->execute(['admin', '$2y$10$veKdW7ikV0LBJg6Dt8Auc.Cc/4kM6o4ywvuWYa.eT1t8wu810KWnq', 'Admin']);
        // user / 02-02-2002
        $stmt_insert->execute(['user', '$2y$10$fKFqGTVCAeqRjGuLxgvUGO4hQBaMq.sHJB/IflPag8dnyH91GfklC', 'User']);
    }
} catch (\PDOException $e) {
     die("Connection failed: " . $e->getMessage());
}
?>