<?php
// import_students.php
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

if ($userRole !== 'Admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only administrators can import students.']);
    exit();
}

$gradeMapping = [
    '1' => 'First Grade', '01' => 'First Grade', 'first' => 'First Grade',
    '2' => 'Second Grade', '02' => 'Second Grade', 'second' => 'Second Grade',
    '3' => 'Third Grade', '03' => 'Third Grade', 'third' => 'Third Grade',
    '4' => 'Fourth Grade', '04' => 'Fourth Grade', 'fourth' => 'Fourth Grade',
    '5' => 'Fifth Grade', '05' => 'Fifth Grade', 'fifth' => 'Fifth Grade',
    '6' => 'Sixth Grade', '06' => 'Sixth Grade', 'sixth' => 'Sixth Grade',
    '7' => 'Seventh Grade', '07' => 'Seventh Grade', 'seventh' => 'Seventh Grade',
    '8' => 'Eighth Grade', '08' => 'Eighth Grade', 'eighth' => 'Eighth Grade',
    '9' => 'Ninth Grade', '09' => 'Ninth Grade', 'ninth' => 'Ninth Grade',
    '10' => 'Tenth Grade', 'tenth' => 'Tenth Grade',
    '11' => 'Eleventh Grade', 'eleventh' => 'Eleventh Grade',
    '12' => 'Twelfth Grade', 'twelfth' => 'Twelfth Grade'
];

if ($action === 'preview') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!is_array($data) || empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty data provided.']);
        exit();
    }
    
    $results = [];
    $total = count($data);
    $valid = 0;
    $duplicatesId = 0;
    $duplicatesEmail = 0;
    $missingFields = 0;
    $invalidRows = 0;
    
    $grades = [
        "First Grade", "Second Grade", "Third Grade", "Fourth Grade",
        "Fifth Grade", "Sixth Grade", "Seventh Grade", "Eighth Grade",
        "Ninth Grade", "Tenth Grade", "Eleventh Grade", "Twelfth Grade"
    ];
    $genders = ["Male", "Female", "Other"];
    
    foreach ($data as $row) {
        $studentName = trim($row['Student Name'] ?? '');
        $studentId = trim($row['Student ID'] ?? '');
        $dob = trim($row['DOB'] ?? '');
        $gender = trim($row['Gender'] ?? '');
        $email = trim($row['Email'] ?? '');
        $phone = trim($row['Phone'] ?? '');
        $address = trim($row['Address'] ?? '');
        $grade = trim($row['Grade'] ?? '');
        
        // Auto-fill defaults if missing
        if (empty($dob)) $dob = "2000-01-01";
        if (empty($email)) $email = strtolower($studentId) . "@school.com";
        if (empty($phone)) $phone = "0000000000";
        if (empty($address)) $address = "Not Provided";
        
        // Map numerical grades
        $gradeKey = strtolower($grade);
        if (isset($gradeMapping[$gradeKey])) {
            $grade = $gradeMapping[$gradeKey];
        }
        
        // Update row so frontend shows the auto-filled data
        $row['DOB'] = $dob;
        $row['Email'] = $email;
        $row['Phone'] = $phone;
        $row['Address'] = $address;
        $row['Grade'] = $grade;
        
        $errors = [];
        
        if (empty($studentName) || empty($studentId) || empty($gender) || empty($grade)) {
            $errors[] = "Missing required fields";
        }
        
        if (!empty($gender) && !in_array($gender, $genders)) {
            $errors[] = "Invalid Gender";
        }
        if (!empty($grade) && !in_array($grade, $grades)) {
            $errors[] = "Invalid Grade";
        }
        
        $dobTime = strtotime(str_replace('/', '-', $dob));
        if (!$dobTime) {
            $errors[] = "Invalid DOB format";
        }
        
        // Check Duplicate Student ID
        $stmtId = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
        $stmtId->execute([$studentId]);
        if ($stmtId->fetchColumn() > 0) {
            $errors[] = "Duplicate Student ID";
            $duplicatesId++;
        }
        
        // Check Duplicate Email
        $stmtEmail = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->fetchColumn() > 0) {
            $errors[] = "Duplicate Email";
            $duplicatesEmail++;
        }
        
        if (in_array("Missing required fields", $errors)) {
            $missingFields++;
        }
        
        $isValid = count($errors) === 0;
        if (!$isValid) {
            $invalidRows++;
        } else {
            $valid++;
        }
        
        $results[] = [
            'row' => $row,
            'isValid' => $isValid,
            'errors' => implode(", ", $errors)
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total' => $total,
            'valid' => $valid,
            'duplicatesId' => $duplicatesId,
            'duplicatesEmail' => $duplicatesEmail,
            'missingFields' => $missingFields,
            'invalidRows' => $invalidRows
        ],
        'data' => $results
    ]);
    exit();

} elseif ($action === 'import') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!is_array($data) || empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty data provided.']);
        exit();
    }
    
    $imported = 0;
    $skippedId = 0;
    $skippedInvalid = 0;
    
    foreach ($data as $item) {
        $row = $item['row'];
        $studentName = trim($row['Student Name'] ?? '');
        $studentId = trim($row['Student ID'] ?? '');
        $dob = trim($row['DOB'] ?? '2000-01-01');
        $gender = trim($row['Gender'] ?? '');
        $email = trim($row['Email'] ?? strtolower($studentId) . '@school.com');
        $phone = trim($row['Phone'] ?? '0000000000');
        $address = trim($row['Address'] ?? 'Not Provided');
        $grade = trim($row['Grade'] ?? '');
        
        $gradeKey = strtolower($grade);
        if (isset($gradeMapping[$gradeKey])) {
            $grade = $gradeMapping[$gradeKey];
        }
        
        $dobTime = strtotime(str_replace('/', '-', $dob));
        $dobFormatted = $dobTime ? date('Y-m-d', $dobTime) : '2000-01-01';
        
        if (empty($studentName) || empty($studentId) || empty($gender) || empty($grade)) {
            $skippedInvalid++;
            continue;
        }
        
        $stmtId = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
        $stmtId->execute([$studentId]);
        if ($stmtId->fetchColumn() > 0) {
            $skippedId++;
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            $photoPath = 'uploads/default_student.png';
            
            $stmt = $pdo->prepare("INSERT INTO students (student_id, student_name, date_of_birth, gender, email, mobile, address, grade, role, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Student', ?)");
            $stmt->execute([$studentId, $studentName, $dobFormatted, $gender, $email, $phone, $address, $grade, $photoPath]);
            $new_db_id = $pdo->lastInsertId();
            
            $password_hashed = password_hash(date('d-m-Y', strtotime($dobFormatted)), PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, is_active, status, email) VALUES (?, ?, 'Student', 1, 'Approved', ?)");
            $stmt_user->execute([$studentId, $password_hashed, $email]);

            $stmt_fee = $pdo->prepare("SELECT id, total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
            $stmt_fee->execute([$grade]);
            $fee_struct = $stmt_fee->fetch(PDO::FETCH_ASSOC);
            if ($fee_struct) {
                $stmt_ins_fee = $pdo->prepare("INSERT INTO student_fees (student_id, fee_structure_id, total_fee, pending_amount) VALUES (?, ?, ?, ?)");
                $stmt_ins_fee->execute([$new_db_id, $fee_struct['id'], $fee_struct['total_fee'], $fee_struct['total_fee']]);
            }
            $pdo->commit();
            $imported++;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $skippedInvalid++;
        }
    }
    
    $_SESSION['toast'] = ['type' => 'success', 'msg' => "Successfully imported $imported students. Skipped $skippedId duplicate records. Skipped $skippedInvalid invalid records."];

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully imported $imported students."
    ]);
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit();
}
