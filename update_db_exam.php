<?php
require_once 'config/db.php';

echo "<h2>Starting Exam Module Database Migration...</h2>";

try {
    // 1. Create exams table
    $sql1 = "CREATE TABLE IF NOT EXISTS `exams` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `exam_name` VARCHAR(255) NOT NULL,
        `class_id` INT NOT NULL,
        `exam_type` VARCHAR(100) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` VARCHAR(50) DEFAULT 'Active',
        `is_published` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql1);
    echo "<p>✅ 'exams' table created or already exists.</p>";

    // 2. Create exam_timetable table
    $sql2 = "CREATE TABLE IF NOT EXISTS `exam_timetable` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `exam_id` INT NOT NULL,
        `class_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `exam_date` DATE NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql2);
    echo "<p>✅ 'exam_timetable' table created or already exists.</p>";

    // 3. Create exam_halls table
    $sql3 = "CREATE TABLE IF NOT EXISTS `exam_halls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `hall_name` VARCHAR(100) NOT NULL,
        `hall_code` VARCHAR(50) NOT NULL,
        `capacity` INT NOT NULL,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql3);
    echo "<p>✅ 'exam_halls' table created or already exists.</p>";

    // 4. Create hall_allotments table
    $sql4 = "CREATE TABLE IF NOT EXISTS `hall_allotments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `exam_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `hall_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`hall_id`) REFERENCES `exam_halls`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql4);
    echo "<p>✅ 'hall_allotments' table created or already exists.</p>";

    echo "<h3>🎉 Exam Module Database Migration Completed Successfully!</h3>";

} catch (PDOException $e) {
    echo "<h3>❌ Error during migration:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
