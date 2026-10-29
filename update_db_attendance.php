<?php
// update_db_attendance.php
require_once __DIR__ . '/config/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `attendance` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "Attendance table created successfully.\n";

} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
