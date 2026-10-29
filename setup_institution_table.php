<?php
require_once __DIR__ . '/config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `institution_profile` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `school_name` VARCHAR(255) NOT NULL,
        `institution_code` VARCHAR(50) NOT NULL,
        `board` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NOT NULL,
        `address` TEXT NOT NULL,
        `principal` VARCHAR(255) NOT NULL,
        `website` VARCHAR(255) NOT NULL,
        `established_year` VARCHAR(10) NOT NULL,
        `total_students` INT NOT NULL,
        `total_staff` INT NOT NULL,
        `logo_path` VARCHAR(255) NOT NULL DEFAULT 'uploads/institution_logo.png',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table institution_profile created successfully.\n";

    // Insert default data if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM institution_profile");
    if ($stmt->fetchColumn() == 0) {
        $insert = "INSERT INTO `institution_profile` 
        (`school_name`, `institution_code`, `board`, `email`, `phone`, `address`, `principal`, `website`, `established_year`, `total_students`, `total_staff`) 
        VALUES 
        ('Smart Campus Matric Higher Secondary School', 'SCMHSS-2000', 'State Board of Tamil Nadu', 'smartcampus@gmail.com', '8300844322', 'Smart Campus Matric Higher Secondary School\nNo. 45, Knowledge Avenue,\nEducational Nagar,\nKarur,\nTamil Nadu - 639001,\nIndia', 'Lakshmi', 'greencampus.in', '2000', 5000, 125)";
        $pdo->exec($insert);
        echo "Default institution data inserted.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
