<?php
// ajax_report_handler.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthenticated access.']);
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Students should not have access to reports
if ($userRole === 'Student') {
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit();
}

$module = $_POST['module'] ?? '';
$data = [];

try {
    switch ($module) {

        // ─────────────── STUDENTS ───────────────
        case 'students':
            // Join with class_students and classes to get section
            $sql = "SELECT s.student_id, s.student_name, s.grade, COALESCE(c.section, '') as section,
                           s.gender, s.email, s.mobile as phone, s.deleted_at, s.created_at as registration_date
                    FROM students s
                    LEFT JOIN class_students cs ON s.id = cs.student_id
                    LEFT JOIN classes c ON cs.class_id = c.id
                    WHERE 1=1";
            $params = [];

            if (!empty($_POST['date_from'])) { $sql .= " AND DATE(s.created_at) >= ?"; $params[] = $_POST['date_from']; }
            if (!empty($_POST['date_to']))   { $sql .= " AND DATE(s.created_at) <= ?"; $params[] = $_POST['date_to']; }
            if (!empty($_POST['grade']))     { $sql .= " AND s.grade LIKE ?";             $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['section']))   { $sql .= " AND c.section LIKE ?";        $params[] = '%' . $_POST['section'] . '%'; }
            if (!empty($_POST['status'])) {
                if ($_POST['status'] === 'active')   $sql .= " AND s.deleted_at IS NULL";
                elseif ($_POST['status'] === 'inactive') $sql .= " AND s.deleted_at IS NOT NULL";
            }
            if (!empty($_POST['gender'])) { $sql .= " AND s.gender = ?";               $params[] = $_POST['gender']; }
            if (!empty($_POST['name']))   { $sql .= " AND s.student_name LIKE ?";       $params[] = '%' . $_POST['name'] . '%'; }
            if (!empty($_POST['id']))     { $sql .= " AND s.student_id LIKE ?";         $params[] = '%' . $_POST['id'] . '%'; }

            $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Student ID'        => $row['student_id'],
                    'Student Name'      => $row['student_name'],
                    'Grade'             => $row['grade'],
                    'Section'           => $row['section'] ?: '-',
                    'Gender'            => $row['gender'] ?: '-',
                    'Email'             => $row['email'] ?: '-',
                    'Phone'             => $row['phone'] ?: '-',
                    'Status'            => is_null($row['deleted_at']) ? 'Active' : 'Inactive',
                    'Registration Date' => date('d-m-Y', strtotime($row['registration_date']))
                ];
            }
            break;

        // ─────────────── STAFF ───────────────
        case 'staff':
            $sql = "SELECT staff_id, staff_name, major_subject, email, phone_number, status, created_at
                    FROM staff WHERE 1=1";
            $params = [];

            if (!empty($_POST['join_from'])) { $sql .= " AND DATE(created_at) >= ?"; $params[] = $_POST['join_from']; }
            if (!empty($_POST['join_to']))   { $sql .= " AND DATE(created_at) <= ?"; $params[] = $_POST['join_to']; }
            if (isset($_POST['status']) && $_POST['status'] !== '') { $sql .= " AND status = ?"; $params[] = $_POST['status']; }
            if (!empty($_POST['subject']))   { $sql .= " AND major_subject LIKE ?";  $params[] = '%' . $_POST['subject'] . '%'; }
            if (!empty($_POST['name']))      { $sql .= " AND staff_name LIKE ?";     $params[] = '%' . $_POST['name'] . '%'; }
            if (!empty($_POST['id']))        { $sql .= " AND staff_id LIKE ?";       $params[] = '%' . $_POST['id'] . '%'; }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Staff ID'      => $row['staff_id'],
                    'Name'          => $row['staff_name'],
                    'Major Subject' => $row['major_subject'] ?: '-',
                    'Email'         => $row['email'] ?: '-',
                    'Phone'         => $row['phone_number'] ?: '-',
                    'Status'        => $row['status'] == 1 ? 'Active' : 'Inactive',
                    'Joined'        => date('d-m-Y', strtotime($row['created_at']))
                ];
            }
            break;

        // ─────────────── ADMINS ───────────────
        case 'admins':
            $sql = "SELECT admin_id, admin_name, email, phone_number, major_subject, status, created_at
                    FROM admins WHERE 1=1";
            $params = [];

            if (isset($_POST['status']) && $_POST['status'] !== '') { $sql .= " AND status = ?";           $params[] = $_POST['status']; }
            if (!empty($_POST['name']))         { $sql .= " AND admin_name LIKE ?";  $params[] = '%' . $_POST['name'] . '%'; }
            if (!empty($_POST['id']))           { $sql .= " AND admin_id LIKE ?";    $params[] = '%' . $_POST['id'] . '%'; }
            if (!empty($_POST['created_date'])) { $sql .= " AND DATE(created_at) = ?"; $params[] = $_POST['created_date']; }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Admin ID'      => $row['admin_id'],
                    'Name'          => $row['admin_name'],
                    'Email'         => $row['email'] ?: '-',
                    'Phone'         => $row['phone_number'] ?: '-',
                    'Major Subject' => $row['major_subject'] ?: '-',
                    'Status'        => $row['status'] == 1 ? 'Active' : 'Inactive',
                    'Created'       => date('d-m-Y', strtotime($row['created_at']))
                ];
            }
            break;

        // ─────────────── CLASSES ───────────────
        case 'classes':
            $sql = "SELECT c.class_name, c.grade, c.section,
                           COALESCE(s.staff_name, '-') as class_teacher,
                           (SELECT COUNT(*) FROM class_students cs JOIN students st ON cs.student_id = st.id
                            WHERE cs.class_id = c.id AND st.deleted_at IS NULL) as total_students,
                           (SELECT COUNT(*) FROM subjects sub WHERE sub.class_id = c.id AND sub.deleted_at IS NULL) as total_subjects,
                           c.deleted_at
                    FROM classes c
                    LEFT JOIN staff s ON c.class_teacher_id = s.id
                    WHERE 1=1";
            $params = [];

            if (!empty($_POST['grade']))   { $sql .= " AND c.grade LIKE ?";   $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['section'])) { $sql .= " AND c.section LIKE ?"; $params[] = '%' . $_POST['section'] . '%'; }
            if (isset($_POST['status']) && $_POST['status'] !== '') {
                if ($_POST['status'] == '1') $sql .= " AND c.deleted_at IS NULL";
                else                          $sql .= " AND c.deleted_at IS NOT NULL";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Class Name'     => $row['class_name'],
                    'Grade'          => $row['grade'],
                    'Section'        => $row['section'],
                    'Class Teacher'  => $row['class_teacher'],
                    'Total Students' => $row['total_students'],
                    'Total Subjects' => $row['total_subjects'],
                    'Status'         => is_null($row['deleted_at']) ? 'Active' : 'Inactive'
                ];
            }
            break;

        // ─────────────── SUBJECTS ───────────────
        case 'subjects':
            $sql = "SELECT sub.subject_name, sub.subject_code,
                           c.grade, c.section, c.class_name,
                           COALESCE(GROUP_CONCAT(DISTINCT st.staff_name SEPARATOR ', '), '-') as assigned_staff
                    FROM subjects sub
                    JOIN classes c ON sub.class_id = c.id
                    LEFT JOIN subject_staff ss ON sub.id = ss.subject_id
                    LEFT JOIN staff st ON ss.staff_id = st.id
                    WHERE sub.deleted_at IS NULL";
            $params = [];

            if (!empty($_POST['grade']))   { $sql .= " AND c.grade LIKE ?";            $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['subject'])) { $sql .= " AND sub.subject_name LIKE ?";   $params[] = '%' . $_POST['subject'] . '%'; }

            $sql .= " GROUP BY sub.id ORDER BY c.grade, c.section, sub.subject_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Subject'        => $row['subject_name'],
                    'Subject Code'   => $row['subject_code'],
                    'Class'          => $row['class_name'],
                    'Grade'          => $row['grade'],
                    'Section'        => $row['section'],
                    'Assigned Staff' => $row['assigned_staff']
                ];
            }
            break;

        // ─────────────── MARKS ───────────────
        case 'marks':
            $sql = "SELECT s.student_id, s.student_name, c.grade, c.section,
                           sub.subject_name, sm.marks,
                           CASE WHEN sm.marks IS NULL THEN '-'
                                WHEN sm.marks >= 90 THEN 'A+'
                                WHEN sm.marks >= 75 THEN 'A'
                                WHEN sm.marks >= 60 THEN 'B'
                                WHEN sm.marks >= 50 THEN 'C'
                                WHEN sm.marks >= 35 THEN 'D'
                                ELSE 'F' END as grade_letter
                    FROM student_marks sm
                    JOIN students s ON sm.student_id = s.id
                    JOIN subjects sub ON sm.subject_id = sub.id
                    JOIN classes c ON sub.class_id = c.id
                    WHERE s.deleted_at IS NULL";
            $params = [];

            if (!empty($_POST['grade']))    { $sql .= " AND c.grade LIKE ?";        $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['section']))  { $sql .= " AND c.section LIKE ?";      $params[] = '%' . $_POST['section'] . '%'; }
            if (!empty($_POST['subject']))  { $sql .= " AND sub.subject_name LIKE ?"; $params[] = '%' . $_POST['subject'] . '%'; }
            if (!empty($_POST['student']))  { $sql .= " AND s.student_name LIKE ?"; $params[] = '%' . $_POST['student'] . '%'; }
            if (!empty($_POST['date_from'])) { $sql .= " AND DATE(sm.updated_at) >= ?"; $params[] = $_POST['date_from']; }
            if (!empty($_POST['date_to']))   { $sql .= " AND DATE(sm.updated_at) <= ?"; $params[] = $_POST['date_to']; }

            $sql .= " ORDER BY c.grade, c.section, s.student_name, sub.subject_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Student ID'   => $row['student_id'],
                    'Student Name' => $row['student_name'],
                    'Grade'        => $row['grade'],
                    'Section'      => $row['section'],
                    'Subject'      => $row['subject_name'],
                    'Marks'        => $row['marks'] ?? '-',
                    'Grade Letter' => $row['grade_letter']
                ];
            }
            break;

        // ─────────────── ATTENDANCE ───────────────
        case 'attendance':
            $sql = "SELECT s.student_id, s.student_name, c.grade, c.section,
                           a.status as attendance_status, a.attendance_date
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    LEFT JOIN class_students cs ON s.id = cs.student_id
                    LEFT JOIN classes c ON cs.class_id = c.id AND cs.class_id = a.class_id
                    WHERE s.deleted_at IS NULL";
            $params = [];

            if (!empty($_POST['date_from']))   { $sql .= " AND a.attendance_date >= ?"; $params[] = $_POST['date_from']; }
            if (!empty($_POST['date_to']))     { $sql .= " AND a.attendance_date <= ?"; $params[] = $_POST['date_to']; }
            if (!empty($_POST['grade']))       { $sql .= " AND c.grade LIKE ?";          $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['section']))     { $sql .= " AND c.section LIKE ?";        $params[] = '%' . $_POST['section'] . '%'; }
            if (!empty($_POST['student']))     { $sql .= " AND s.student_name LIKE ?";   $params[] = '%' . $_POST['student'] . '%'; }
            // The form field is named 'status' for attendance
            if (!empty($_POST['status']))      { $sql .= " AND a.status = ?";            $params[] = $_POST['status']; }

            $sql .= " ORDER BY a.attendance_date DESC, c.grade, s.student_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Student ID'   => $row['student_id'],
                    'Student Name' => $row['student_name'],
                    'Grade'        => $row['grade'] ?: '-',
                    'Section'      => $row['section'] ?: '-',
                    'Status'       => $row['attendance_status'],
                    'Date'         => date('d-m-Y', strtotime($row['attendance_date']))
                ];
            }
            break;

        // ─────────────── FEES ───────────────
        case 'fees':
            $sql = "SELECT fp.receipt_no, fp.payment_date, fp.payment_method,
                           fp.amount_paid, fp.remarks, sf.status as fee_status,
                           sf.total_fee, sf.paid_amount, sf.pending_amount,
                           s.student_id, s.student_name, s.grade
                    FROM fee_payments fp
                    JOIN student_fees sf ON fp.student_fee_id = sf.id
                    JOIN students s ON sf.student_id = s.id
                    WHERE s.deleted_at IS NULL";
            $params = [];

            if (!empty($_POST['grade']))    { $sql .= " AND s.grade LIKE ?";             $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['student']))  { $sql .= " AND s.student_name LIKE ?";      $params[] = '%' . $_POST['student'] . '%'; }
            if (!empty($_POST['status']))   { $sql .= " AND sf.status = ?";              $params[] = $_POST['status']; }
            if (!empty($_POST['date_from'])) { $sql .= " AND fp.payment_date >= ?";     $params[] = $_POST['date_from']; }
            if (!empty($_POST['date_to']))   { $sql .= " AND fp.payment_date <= ?";     $params[] = $_POST['date_to']; }

            $sql .= " ORDER BY fp.payment_date DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Receipt No'     => $row['receipt_no'],
                    'Student ID'     => $row['student_id'],
                    'Student Name'   => $row['student_name'],
                    'Grade'          => $row['grade'],
                    'Payment Date'   => date('d-m-Y', strtotime($row['payment_date'])),
                    'Payment Method' => $row['payment_method'],
                    'Amount Paid'    => number_format($row['amount_paid'], 2),
                    'Total Fee'      => number_format($row['total_fee'], 2),
                    'Pending'        => number_format($row['pending_amount'], 2),
                    'Fee Status'     => $row['fee_status'],
                    'Remarks'        => $row['remarks'] ?: '-'
                ];
            }
            break;

        // ─────────────── EXAM SCHEDULE ───────────────
        case 'exam_schedule':
            $sql = "SELECT e.exam_name, e.exam_type, e.start_date, e.end_date, e.status,
                           c.class_name, c.grade, c.section,
                           et.exam_date, et.start_time, et.end_time,
                           sub.subject_name
                    FROM exams e
                    JOIN classes c ON e.class_id = c.id
                    LEFT JOIN exam_timetable et ON et.exam_id = e.id
                    LEFT JOIN subjects sub ON et.subject_id = sub.id
                    WHERE 1=1";
            $params = [];

            if (!empty($_POST['grade']))     { $sql .= " AND c.grade LIKE ?";          $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['subject']))   { $sql .= " AND sub.subject_name LIKE ?"; $params[] = '%' . $_POST['subject'] . '%'; }
            if (!empty($_POST['date']))      { $sql .= " AND et.exam_date = ?";         $params[] = $_POST['date']; }
            if (!empty($_POST['exam_type'])) { $sql .= " AND e.exam_type = ?";         $params[] = $_POST['exam_type']; }

            $sql .= " ORDER BY e.start_date, c.grade, et.exam_date";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Exam Name'   => $row['exam_name'],
                    'Exam Type'   => $row['exam_type'],
                    'Class'       => $row['class_name'],
                    'Grade'       => $row['grade'],
                    'Section'     => $row['section'],
                    'Subject'     => $row['subject_name'] ?: '-',
                    'Exam Date'   => $row['exam_date'] ? date('d-m-Y', strtotime($row['exam_date'])) : '-',
                    'Start Time'  => $row['start_time'] ?: '-',
                    'End Time'    => $row['end_time'] ?: '-',
                    'Status'      => $row['status']
                ];
            }
            break;

        // ─────────────── HALL ALLOTMENT ───────────────
        case 'hall_allotment':
            $sql = "SELECT s.student_id, s.student_name, c.grade, c.section,
                           eh.hall_name, eh.hall_code,
                           e.exam_name, e.exam_type
                    FROM hall_allotments ha
                    JOIN students s ON ha.student_id = s.id
                    JOIN exam_halls eh ON ha.hall_id = eh.id
                    JOIN exams e ON ha.exam_id = e.id
                    JOIN classes c ON e.class_id = c.id
                    WHERE s.deleted_at IS NULL";
            $params = [];

            if (!empty($_POST['grade']))     { $sql .= " AND c.grade LIKE ?";          $params[] = '%' . $_POST['grade'] . '%'; }
            if (!empty($_POST['section']))   { $sql .= " AND c.section LIKE ?";        $params[] = '%' . $_POST['section'] . '%'; }
            if (!empty($_POST['hall_name'])) { $sql .= " AND eh.hall_name LIKE ?";     $params[] = '%' . $_POST['hall_name'] . '%'; }
            if (!empty($_POST['student']))   { $sql .= " AND s.student_name LIKE ?";   $params[] = '%' . $_POST['student'] . '%'; }

            $sql .= " ORDER BY c.grade, c.section, s.student_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data[] = [
                    'Student ID'   => $row['student_id'],
                    'Student Name' => $row['student_name'],
                    'Grade'        => $row['grade'],
                    'Section'      => $row['section'],
                    'Exam'         => $row['exam_name'],
                    'Exam Type'    => $row['exam_type'],
                    'Hall Name'    => $row['hall_name'],
                    'Hall Code'    => $row['hall_code']
                ];
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Module not supported.']);
            exit();
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
