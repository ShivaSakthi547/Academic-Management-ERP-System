<?php
// sidebar.php

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$userRole = $_SESSION['user_role'] ?? '';
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-transparent" style="width: 100%; min-height: 100vh;">
    <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="bi bi-list-columns-reverse text-primary me-2 fs-4"></i>
        <span class="fs-4 fw-bold">Menu</span>
    </a>
    <hr class="text-secondary border-secondary">
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php' && empty($_GET['filter'])) ? 'active' : ''; ?>" aria-current="page">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-circle me-2"></i>
                Profile
            </a>
        </li>

        <?php if ($userRole === 'Admin'): ?>
            <li class="nav-item">
                <a href="send_notification.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'send_notification.php') ? 'active' : ''; ?>">
                    <i class="bi bi-bell-fill me-2"></i>
                    Send Notification
                </a>
            </li>

            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">User Approvals</span>
            </li>
            <li class="nav-item">
                <a href="user_approvals.php?status=Pending" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_approvals.php' && ($_GET['status'] ?? '') == 'Pending') ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split me-2"></i>
                    Pending Users
                </a>
            </li>
            <li class="nav-item">
                <a href="user_approvals.php?status=Approved" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_approvals.php' && ($_GET['status'] ?? '') == 'Approved') ? 'active' : ''; ?>">
                    <i class="bi bi-person-check-fill me-2"></i>
                    Approved Users
                </a>
            </li>
            <li class="nav-item">
                <a href="user_approvals.php?status=Rejected" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_approvals.php' && ($_GET['status'] ?? '') == 'Rejected') ? 'active' : ''; ?>">
                    <i class="bi bi-person-x-fill me-2"></i>
                    Rejected Users
                </a>
            </li>

            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Students</span>
            </li>
            <li class="nav-item">
                <a href="student_form.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_form.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-plus me-2"></i>
                    Add Student
                </a>
            </li>
            <li class="nav-item">
                <a href="view_students.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_students.php') ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill me-2"></i>
                    View Students
                </a>
            </li>

            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Admins</span>
            </li>
            <li class="nav-item">
                <a href="admin_form.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_form.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-plus me-2"></i>
                    Add Admin
                </a>
            </li>
            <li class="nav-item">
                <a href="view_admins.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_admins.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-lines-fill me-2"></i>
                    View Admins
                </a>
            </li>

            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Staff</span>
            </li>
            <li class="nav-item">
                <a href="staff_form.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_form.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Add Staff
                </a>
            </li>
            <li class="nav-item">
                <a href="view_staff.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_staff.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-bounding-box me-2"></i>
                    View Staff
                </a>
            </li>

            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Academic</span>
            </li>
            <li class="nav-item">
                <a href="view_classes.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_classes.php') ? 'active' : ''; ?>">
                    <i class="bi bi-building me-2"></i>
                    Classes
                </a>
            </li>
            <li class="nav-item">
                <a href="view_subjects.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_subjects.php') ? 'active' : ''; ?>">
                    <i class="bi bi-book-half me-2"></i>
                    Subjects
                </a>
            </li>
            <li class="nav-item">
                <a href="marks.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'marks.php' || basename($_SERVER['PHP_SELF']) == 'student_marks.php') ? 'active' : ''; ?>">
                    <i class="bi bi-journal-check me-2"></i>
                    Marks
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php' || basename($_SERVER['PHP_SELF']) == 'attendance_sheet.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check me-2"></i>
                    Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance_history.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_history.php') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history me-2"></i>
                    Attendance History
                </a>
            </li>
            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Exams</span>
            </li>
            <li class="nav-item">
                <a href="exam_schedule.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'exam_schedule.php' || basename($_SERVER['PHP_SELF']) == 'class_exam_details.php' || basename($_SERVER['PHP_SELF']) == 'hall_allotment.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event me-2"></i>
                    Exam Schedule
                </a>
            </li>
            <li class="nav-item">
                <a href="hall_management.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'hall_management.php') ? 'active' : ''; ?>">
                    <i class="bi bi-building me-2"></i>
                    Hall Management
                </a>
            </li>
            <li class="nav-item">
                <a href="exam_reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'exam_reports.php') ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-fill me-2"></i>
                    Exam Analytics
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">System</span>
            </li>
            <li class="nav-item">
                <a href="email_logs.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'email_logs.php') ? 'active' : ''; ?>">
                    <i class="bi bi-envelope-paper-fill me-2"></i>
                    Email Logs
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <span class="nav-link text-muted small fw-bold text-uppercase pb-1">Fees Management</span>
            </li>
            <li class="nav-item">
                <a href="fee_structure.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'fee_structure.php') ? 'active' : ''; ?>">
                    <i class="bi bi-cash-stack me-2"></i>
                    Fee Structure
                </a>
            </li>
            <li class="nav-item">
                <a href="collect_fee.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'collect_fee.php') ? 'active' : ''; ?>">
                    <i class="bi bi-wallet2 me-2"></i>
                    Collect Fee
                </a>
            </li>
            <li class="nav-item">
                <a href="fee_history.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'fee_history.php') ? 'active' : ''; ?>">
                    <i class="bi bi-receipt me-2"></i>
                    Payment History
                </a>
            </li>

        <?php elseif ($userRole === 'Staff'): ?>
            <li class="nav-item">
                <a href="send_notification.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'send_notification.php') ? 'active' : ''; ?>">
                    <i class="bi bi-bell-fill me-2"></i>
                    Send Notification
                </a>
            </li>
            <li class="nav-item">
                <a href="view_students.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'view_students.php') ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill me-2"></i>
                    Students
                </a>
            </li>
            <li class="nav-item">
                <a href="marks.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'marks.php' || basename($_SERVER['PHP_SELF']) == 'student_marks.php') ? 'active' : ''; ?>">
                    <i class="bi bi-journal-check me-2"></i>
                    Marks
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php' || basename($_SERVER['PHP_SELF']) == 'attendance_sheet.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check me-2"></i>
                    Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance_history.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_history.php') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history me-2"></i>
                    Attendance History
                </a>
            </li>
            <li class="nav-item">
                <a href="staff_exam_schedule.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_exam_schedule.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event me-2"></i>
                    Exam Schedule
                </a>
            </li>
            <li class="nav-item">
                <a href="staff_fees.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_fees.php') ? 'active' : ''; ?>">
                    <i class="bi bi-wallet2 me-2"></i>
                    Fees
                </a>
            </li>
        <?php elseif ($userRole === 'Student'): ?>
            <li class="nav-item">
                <a href="student_marks_view.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_marks_view.php') ? 'active' : ''; ?>">
                    <i class="bi bi-journal-check me-2"></i>
                    Marks
                </a>
            </li>
            <li class="nav-item">
                <a href="student_exam_schedule.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_exam_schedule.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event me-2"></i>
                    Exam Schedule
                </a>
            </li>
            <li class="nav-item">
                <a href="student_fees.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_fees.php') ? 'active' : ''; ?>">
                    <i class="bi bi-wallet2 me-2"></i>
                    Fees
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>
<script>
    // Asynchronously process email queue without blocking the UI
    setTimeout(() => {
        fetch('process_email_queue.php', { method: 'GET', keepalive: true }).catch(() => {});
    }, 500);
</script>
