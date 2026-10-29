<?php
// update_db_fees.php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Fees Module database update...<br>";

    // 1. fee_structures
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_structures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade VARCHAR(20) NOT NULL UNIQUE,
        tuition_fee DECIMAL(10,2) DEFAULT 0,
        exam_fee DECIMAL(10,2) DEFAULT 0,
        lab_fee DECIMAL(10,2) DEFAULT 0,
        library_fee DECIMAL(10,2) DEFAULT 0,
        transport_fee DECIMAL(10,2) DEFAULT 0,
        miscellaneous_fee DECIMAL(10,2) DEFAULT 0,
        total_fee DECIMAL(10,2) DEFAULT 0,
        status ENUM('Active','Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'fee_structures' verified.<br>";

    // 2. student_fees
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_fees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        fee_structure_id INT NOT NULL,
        total_fee DECIMAL(10,2) DEFAULT 0,
        paid_amount DECIMAL(10,2) DEFAULT 0,
        pending_amount DECIMAL(10,2) DEFAULT 0,
        status ENUM('Paid','Partial','Pending') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `student_fee_unique` (`student_id`),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'student_fees' verified.<br>";

    // 3. fee_payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_fee_id INT NOT NULL,
        receipt_no VARCHAR(100) UNIQUE,
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method ENUM('Cash', 'UPI', 'Card', 'Bank Transfer') NOT NULL,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'fee_payments' verified.<br>";

    // 4. fee_receipts
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        receipt_path VARCHAR(255),
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES fee_payments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'fee_receipts' verified.<br>";

    // Automatically create student_fees records for existing students
    echo "Assigning fee structures to existing students...<br>";
    $stmtStudents = $pdo->query("SELECT id, grade FROM students WHERE deleted_at IS NULL");
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    $stmtStructure = $pdo->prepare("SELECT id, total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
    $stmtInsertFee = $pdo->prepare("INSERT IGNORE INTO student_fees (student_id, fee_structure_id, total_fee, pending_amount) VALUES (?, ?, ?, ?)");

    $assignedCount = 0;
    foreach ($students as $student) {
        $stmtStructure->execute([$student['grade']]);
        $structure = $stmtStructure->fetch(PDO::FETCH_ASSOC);
        
        if ($structure) {
            $stmtInsertFee->execute([
                $student['id'],
                $structure['id'],
                $structure['total_fee'],
                $structure['total_fee']
            ]);
            if ($stmtInsertFee->rowCount() > 0) {
                $assignedCount++;
            }
        }
    }
    echo "$assignedCount fee records assigned.<br>";

    echo "<br><strong style='color:green;'>Fees Module Database Update Completed Successfully!</strong>";

} catch (PDOException $e) {
    echo "<br><strong style='color:red;'>Database Error: " . $e->getMessage() . "</strong>";
}
?>
