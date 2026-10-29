<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending'");
    echo "Added status column.\n";
} catch (PDOException $e) {
    echo "Status column might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) NULL");
    echo "Added full_name column.\n";
} catch (PDOException $e) {
    echo "full_name column might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL");
    echo "Added email column.\n";
} catch (PDOException $e) {
    echo "email column might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN dob DATE NULL");
    echo "Added dob column.\n";
} catch (PDOException $e) {
    echo "dob column might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT NULL");
    echo "Added address column.\n";
} catch (PDOException $e) {
    echo "address column might already exist: " . $e->getMessage() . "\n";
}

// Update existing users to 'Approved' so they don't get locked out
try {
    $pdo->exec("UPDATE users SET status = 'Approved' WHERE status = 'Pending'");
    echo "Updated existing users to Approved.\n";
} catch (PDOException $e) {
    echo "Error updating existing users: " . $e->getMessage() . "\n";
}
echo "Database update completed.\n";
?>
