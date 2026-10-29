<?php
require_once __DIR__ . '/config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS `report_cards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `generated_by` INT NOT NULL,
  `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `pdf_path` VARCHAR(255) NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo "Table report_cards created successfully.\n";
} catch (\PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
