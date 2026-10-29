<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Update admins table
    $pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER address");
    $pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL AFTER email");
    $pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS major_subject VARCHAR(100) NULL AFTER phone_number");

    // 2. Update staff table
    $pdo->exec("ALTER TABLE staff ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER address");
    $pdo->exec("ALTER TABLE staff ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL AFTER email");
    $pdo->exec("ALTER TABLE staff ADD COLUMN IF NOT EXISTS major_subject VARCHAR(100) NULL AFTER phone_number");

    // 3. Create classes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        grade VARCHAR(50) NOT NULL,
        section VARCHAR(50) NOT NULL,
        description TEXT,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Create subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        subject_name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Create class_students table
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        student_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY class_student_unique (class_id, student_id),
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Create subject_staff table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subject_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        staff_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY subject_staff_unique (subject_id, staff_id),
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. Update classes table
    $pdo->exec("ALTER TABLE classes ADD COLUMN IF NOT EXISTS class_teacher_id INT NULL");
    // To safely add foreign key if not exists (MySQL doesn't have IF NOT EXISTS for ADD CONSTRAINT directly, so we check first)
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'class_teacher_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE classes ADD CONSTRAINT fk_class_teacher FOREIGN KEY (class_teacher_id) REFERENCES staff(id) ON DELETE SET NULL");
    }

    $pdo->commit();
    echo "Migration completed successfully.\n";
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
