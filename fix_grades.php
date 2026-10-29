<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("UPDATE fee_structures SET grade = 'Tenth Grade' WHERE grade = '10'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Twelfth Grade' WHERE grade = '12'");
    $pdo->exec("UPDATE fee_structures SET grade = 'First Grade' WHERE grade = '1'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Second Grade' WHERE grade = '2'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Third Grade' WHERE grade = '3'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Fourth Grade' WHERE grade = '4'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Fifth Grade' WHERE grade = '5'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Sixth Grade' WHERE grade = '6'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Seventh Grade' WHERE grade = '7'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Eighth Grade' WHERE grade = '8'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Ninth Grade' WHERE grade = '9'");
    $pdo->exec("UPDATE fee_structures SET grade = 'Eleventh Grade' WHERE grade = '11'");
    echo "Grades updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
