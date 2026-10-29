<?php
// save_attendance.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access Denied. Only staff can save attendance.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id'] ?? 0);
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $attendance_data = $_POST['attendance'] ?? []; // student_id => 'Present' or 'Absent'
    
    $userName = $_SESSION['user_name'];
    
    try {
        $pdo->beginTransaction();
        
        // 1. Verify staff is class teacher
        $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
        $stmt_staff->execute([$userName]);
        $staff_id = $stmt_staff->fetchColumn();
        
        if (!$staff_id) {
            throw new Exception("Staff profile not found.");
        }
        
        $stmt_class = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND class_teacher_id = ?");
        $stmt_class->execute([$class_id, $staff_id]);
        if (!$stmt_class->fetch()) {
            throw new Exception("You are not the class teacher for this class.");
        }
        
        // 2. Validate students belong to the class
        $stmt_students = $pdo->prepare("SELECT student_id FROM class_students WHERE class_id = ?");
        $stmt_students->execute([$class_id]);
        $class_students = $stmt_students->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt_insert = $pdo->prepare("
            INSERT INTO attendance (student_id, class_id, attendance_date, status, marked_by) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
        ");
        
        $processed = 0;
        foreach ($attendance_data as $student_id => $status) {
            if (in_array($student_id, $class_students) && in_array($status, ['Present', 'Absent'])) {
                $stmt_insert->execute([$student_id, $class_id, $attendance_date, $status, $staff_id]);
                $processed++;
            }
        }
        
        $pdo->commit();
        
        // Attendance Alert Logic
        require_once __DIR__ . '/includes/Mailer.php';
        $mailer = new Mailer($pdo);
        $consecutive_days_limit = 3;
        
        foreach ($attendance_data as $student_id => $status) {
            if ($status === 'Absent' && in_array($student_id, $class_students)) {
                // Check last 3 consecutive days
                $stmt_chk = $pdo->prepare("
                    SELECT status 
                    FROM attendance 
                    WHERE student_id = ? AND attendance_date <= ?
                    ORDER BY attendance_date DESC 
                    LIMIT ?
                ");
                // Note: consecutive_days_limit is dynamically passed, but PDO limit binding needs type INT if emulated prepare is off. 
                // We will use bindValue or just hardcode the limit 3 if it's simpler, or bindParam.
                // Let's hardcode the limit query and use parameters for the rest to be safe
                $stmt_chk->bindValue(1, $student_id, PDO::PARAM_INT);
                $stmt_chk->bindValue(2, $attendance_date, PDO::PARAM_STR);
                $stmt_chk->bindValue(3, $consecutive_days_limit, PDO::PARAM_INT);
                $stmt_chk->execute();
                
                $last_records = $stmt_chk->fetchAll(PDO::FETCH_COLUMN);
                
                $is_consecutively_absent = true;
                if (count($last_records) < $consecutive_days_limit) {
                    $is_consecutively_absent = false;
                } else {
                    foreach ($last_records as $rec_status) {
                        if ($rec_status !== 'Absent') {
                            $is_consecutively_absent = false;
                            break;
                        }
                    }
                }
                
                if ($is_consecutively_absent) {
                    $stmt_att_stat = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as pres FROM attendance WHERE student_id = ?");
                    $stmt_att_stat->execute([$student_id]);
                    $att_stat = $stmt_att_stat->fetch();
                    $att_perc = $att_stat['tot'] > 0 ? round(($att_stat['pres'] / $att_stat['tot']) * 100, 2) : 0;
                    
                    $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
                    $stmt_stu->execute([$student_id]);
                    $stu_data = $stmt_stu->fetch();
                    
                    if ($stu_data && !empty($stu_data['email'])) {
                        $subject = "Attendance Alert";
                        $body = "<h3>Attendance Alert</h3>
                                 <p>Dear {$stu_data['student_name']},</p>
                                 <p>This is to inform you that the student has been absent for $consecutive_days_limit consecutive days.</p>
                                 <p>Current overall attendance is <strong>{$att_perc}%</strong>.</p>
                                 <p>Please contact the school administration immediately.</p>";
                        $mailer->sendEmail($stu_data['email'], $subject, $body);
                    }
                }
            }
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'msg' => "Attendance saved for $processed students."];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Error saving attendance: ' . $e->getMessage()];
    }
    
    header("Location: attendance_sheet.php?class_id=$class_id&date=$attendance_date");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>
