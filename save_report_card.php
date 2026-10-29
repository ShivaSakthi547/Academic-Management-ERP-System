<?php
ob_start();
// save_report_card.php
session_start();

header('Content-Type: application/json');

function send_json_response($data) {
    ob_clean();
    echo json_encode($data);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'message' => 'Unauthenticated']);
}

$userRole = $_SESSION['user_role'] ?? '';
if ($userRole !== 'Admin' && $userRole !== 'Staff') {
    send_json_response(['success' => false, 'message' => 'Unauthorized']);
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method']);
}

$student_id = intval($_POST['student_id'] ?? 0);
$class_id = intval($_POST['class_id'] ?? 0);
$pdf_data = $_POST['pdf_data'] ?? '';

if ($student_id <= 0 || $class_id <= 0 || empty($pdf_data)) {
    send_json_response(['success' => false, 'message' => 'Missing required data']);
}

// Extract base64 data robustly
$pos = strpos($pdf_data, 'base64,');
if ($pos !== false) {
    $pdf_data = substr($pdf_data, $pos + 7);
}

// Replace spaces with + in case they were replaced during transmission
$pdf_data = str_replace(' ', '+', $pdf_data);

$pdf_decoded = base64_decode($pdf_data);

if ($pdf_decoded === false || empty($pdf_decoded)) {
    send_json_response(['success' => false, 'message' => 'Failed to decode PDF data']);
}

// Make directory if not exists
$uploadDir = __DIR__ . '/uploads/reports/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        send_json_response(['success' => false, 'message' => 'Failed to create directory']);
    }
}

// Generate filename
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$stu_number = $stmt->fetchColumn() ?: 'Student_' . $student_id;

$filename = preg_replace('/[^A-Za-z0-9_-]/', '', $stu_number) . '_ReportCard_' . time() . '.pdf';
$filepath = $uploadDir . $filename;
$db_path = 'uploads/reports/' . $filename;

if (file_put_contents($filepath, $pdf_decoded) === false) {
    send_json_response(['success' => false, 'message' => 'Failed to write file to disk']);
}

// Validation after PDF creation
if (!file_exists($filepath) || filesize($filepath) === 0) {
    @unlink($filepath);
    send_json_response(['success' => false, 'message' => 'Failed to generate report card PDF. Please regenerate.']);
}

// Verify PDF header
$header = file_get_contents($filepath, false, null, 0, 5);
if ($header !== '%PDF-') {
    @unlink($filepath);
    send_json_response(['success' => false, 'message' => 'Failed to generate report card PDF. Please regenerate.']);
}

try {
    // Insert into DB
    $stmt = $pdo->prepare("INSERT INTO report_cards (student_id, class_id, generated_by, pdf_path) VALUES (?, ?, ?, ?)");
    $stmt->execute([$student_id, $class_id, $_SESSION['user_id'], $db_path]);
    
    // Send Email
    require_once __DIR__ . '/includes/Mailer.php';
    $mailer = new Mailer($pdo);
    
    // Fetch student email and name
    $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
    $stmt_stu->execute([$student_id]);
    $stu_data = $stmt_stu->fetch();
    
    if ($stu_data && !empty($stu_data['email'])) {
        $subject = 'Report Card Published';
        $body = "<h3>Report Card Published</h3>
                 <p>Dear {$stu_data['student_name']},</p>
                 <p>Your report card has been generated and is attached to this email.</p>
                 <p>Please find the detailed report card attached.</p>";
                 
        $pdf_content = file_get_contents($filepath);
        $mailer->sendEmail($stu_data['email'], $subject, $body, $pdf_content, "Report_Card.pdf");
    }
    
    send_json_response(['success' => true, 'path' => $db_path]);
} catch (\PDOException $e) {
    send_json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
