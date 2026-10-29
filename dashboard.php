<?php
// dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Get counts
try {
    $count_active = $pdo->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
    $count_inactive = $pdo->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NOT NULL")->fetchColumn();
    $count_total = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    if ($userRole === 'Admin') {
        $count_pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Pending'")->fetchColumn();
        $count_admins_active = $pdo->query("SELECT COUNT(*) FROM admins WHERE status = 1")->fetchColumn();
        $count_admins_inactive = $pdo->query("SELECT COUNT(*) FROM admins WHERE status = 0")->fetchColumn();
        $count_admins_total = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        $count_staff_active = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 1")->fetchColumn();
        $count_staff_inactive = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 0")->fetchColumn();
        $count_staff_total = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        $count_classes_total = $pdo->query("SELECT COUNT(*) FROM classes WHERE deleted_at IS NULL")->fetchColumn();
        $count_subjects_total = $pdo->query("SELECT COUNT(*) FROM subjects WHERE deleted_at IS NULL")->fetchColumn();
        $count_exams_total = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
        $count_exams_published = $pdo->query("SELECT COUNT(*) FROM exams WHERE is_published = 1")->fetchColumn();
        $count_exams_upcoming = $pdo->query("SELECT COUNT(*) FROM exams WHERE start_date > CURDATE()")->fetchColumn();
        $count_emails_total = $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
        $count_emails_sent = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'Sent'")->fetchColumn();
        $count_emails_failed = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'Failed'")->fetchColumn();
        $count_unassigned_students = $pdo->query("SELECT COUNT(*) FROM students s LEFT JOIN class_students cs ON s.id = cs.student_id WHERE cs.class_id IS NULL AND s.deleted_at IS NULL")->fetchColumn();
        $grade_distribution = $pdo->query("SELECT c.grade, COUNT(cs.student_id) as student_count FROM classes c LEFT JOIN class_students cs ON c.id = cs.class_id WHERE c.deleted_at IS NULL GROUP BY c.grade ORDER BY c.grade")->fetchAll(PDO::FETCH_ASSOC);
        $att_raw = $pdo->query("SELECT status, COUNT(*) as count FROM attendance WHERE attendance_date = CURDATE() GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $attendance_overview = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half-Day' => 0];
        $total_att_today = 0;
        foreach ($att_raw as $row) { $attendance_overview[$row['status']] = $row['count']; $total_att_today += $row['count']; }
        $attendance_percent = $total_att_today > 0 ? round(($attendance_overview['Present'] / $total_att_today) * 100) : 0;
        $fee_collected = 0; $fee_pending = 0;
        try { $fd = $pdo->query("SELECT SUM(paid_amount) as collected, SUM(pending_amount) as pending FROM student_fees")->fetch(PDO::FETCH_ASSOC); $fee_collected = $fd['collected'] ?? 0; $fee_pending = $fd['pending'] ?? 0; } catch(\PDOException $ef){}
        $count_notifications_today = 0;
        try { $count_notifications_today = $pdo->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn(); } catch(\PDOException $en){}
        $recent_activities = [];
        try { $recent_activities = $pdo->query("(SELECT 'Student Registered' as action, student_name as detail, created_at as date FROM students ORDER BY created_at DESC LIMIT 3) UNION ALL (SELECT 'Exam Published' as action, exam_name as detail, updated_at as date FROM exams WHERE is_published = 1 ORDER BY updated_at DESC LIMIT 3) ORDER BY date DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC); } catch(\PDOException $ea){}
    }

    if ($userRole === 'Staff' || $userRole === 'Student') {
        $stmt = $pdo->query("SELECT * FROM exams WHERE is_published = 1 AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 1");
        $latest_published_exam = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($userRole === 'Staff') {
        $userName = $_SESSION['user_name'] ?? '';
        $staff_internal_id = 0;
        $st = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?"); $st->execute([$userName]);
        $staff_internal_id = $st->fetchColumn() ?: 0;
        $count_assigned_classes = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM classes c LEFT JOIN subjects s ON c.id = s.class_id LEFT JOIN subject_staff ss ON s.id = ss.subject_id WHERE (c.class_teacher_id = ? OR ss.staff_id = ?) AND c.deleted_at IS NULL"); $count_assigned_classes->execute([$staff_internal_id, $staff_internal_id]); $count_assigned_classes = $count_assigned_classes->fetchColumn();
        $stq = $pdo->prepare("SELECT COUNT(*) FROM subject_staff WHERE staff_id = ?"); $stq->execute([$staff_internal_id]); $count_assigned_subjects = $stq->fetchColumn();
        $ctq = $pdo->prepare("SELECT class_name, grade, section FROM classes WHERE class_teacher_id = ? AND deleted_at IS NULL"); $ctq->execute([$staff_internal_id]); $class_teacher_of = $ctq->fetchAll(PDO::FETCH_ASSOC);
        $slq = $pdo->prepare("SELECT s.subject_name, c.class_name FROM subjects s JOIN subject_staff ss ON s.id = ss.subject_id JOIN classes c ON s.class_id = c.id WHERE ss.staff_id = ? AND s.deleted_at IS NULL"); $slq->execute([$staff_internal_id]); $assigned_subjects_list = $slq->fetchAll(PDO::FETCH_ASSOC);
        $ueq = $pdo->prepare("SELECT e.exam_name, e.start_date, e.end_date FROM exams e JOIN classes c ON e.class_id = c.id LEFT JOIN subjects sub ON c.id = sub.class_id LEFT JOIN subject_staff ss ON sub.id = ss.subject_id WHERE (c.class_teacher_id = ? OR ss.staff_id = ?) AND e.start_date > CURDATE() LIMIT 5"); $ueq->execute([$staff_internal_id, $staff_internal_id]); $upcoming_exam_duties = $ueq->fetchAll(PDO::FETCH_ASSOC);
        $atq = $pdo->prepare("SELECT a.status, COUNT(*) as count FROM attendance a JOIN classes c ON a.class_id = c.id WHERE c.class_teacher_id = ? AND a.attendance_date = CURDATE() GROUP BY a.status"); $atq->execute([$staff_internal_id]); $atqr = $atq->fetchAll(PDO::FETCH_ASSOC);
        $attendance_overview = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half-Day' => 0]; foreach ($atqr as $row) { $attendance_overview[$row['status']] = $row['count']; }
        $staff_active_students = 0; $staff_inactive_students = 0;
        if ($staff_internal_id) { $sdq = $pdo->prepare("SELECT (s.deleted_at IS NOT NULL) as is_inactive, COUNT(DISTINCT s.id) as cnt FROM students s JOIN class_students cs ON s.id = cs.student_id JOIN classes c ON cs.class_id = c.id LEFT JOIN subjects sub ON c.id = sub.class_id LEFT JOIN subject_staff ss ON sub.id = ss.subject_id WHERE (c.class_teacher_id = ? OR ss.staff_id = ?) GROUP BY is_inactive"); $sdq->execute([$staff_internal_id, $staff_internal_id]); foreach($sdq->fetchAll(PDO::FETCH_ASSOC) as $r) { if($r['is_inactive'] == 0) $staff_active_students = $r['cnt']; else $staff_inactive_students = $r['cnt']; } }
        $staff_total_students = $staff_active_students + $staff_inactive_students;
        $count_unread_notifications = 0;
        try { $uq = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $uq->execute([$userName]); $uid = $uq->fetchColumn(); if ($uid) { $nq = $pdo->prepare("SELECT COUNT(*) FROM notification_recipients WHERE receiver_id = ? AND is_read = 0"); $nq->execute([$uid]); $count_unread_notifications = $nq->fetchColumn(); } } catch(\PDOException $eu){}
        $recent_activities = [];
        try { $raq = $pdo->prepare("(SELECT 'Attendance Submitted' as action, DATE_FORMAT(attendance_date,'%d %b %Y') as detail, created_at as date FROM attendance WHERE marked_by = ? ORDER BY created_at DESC LIMIT 4) UNION ALL (SELECT 'Marks Updated' as action, CONCAT('Subject #',subject_id) as detail, updated_at as date FROM student_marks WHERE updated_at IS NOT NULL ORDER BY updated_at DESC LIMIT 4) ORDER BY date DESC LIMIT 6"); $raq->execute([$userName]); $recent_activities = $raq->fetchAll(PDO::FETCH_ASSOC); } catch(\PDOException $ea){}
    }
} catch (\PDOException $e) {
    die("Database query failed: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f4f7fe; color: #2b3674; }
        .card-custom { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff; transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .card-custom:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(67,24,255,0.12); }
        .kpi-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .grad-primary { background: linear-gradient(135deg, #4318FF 0%, #868CFF 100%); color: white; }
        .grad-success { background: linear-gradient(135deg, #01B574 0%, #34D399 100%); color: white; }
        .grad-warning { background: linear-gradient(135deg, #FFB547 0%, #FFD28D 100%); color: white; }
        .grad-danger { background: linear-gradient(135deg, #EE5D50 0%, #FF8A80 100%); color: white; }
        .grad-info { background: linear-gradient(135deg, #39B8FF 0%, #8CE0FF 100%); color: white; }
        .grad-purple { background: linear-gradient(135deg, #7928CA 0%, #FF0080 100%); color: white; }
        .grad-teal { background: linear-gradient(135deg, #0BBBCC 0%, #47D5E5 100%); color: white; }
        .grad-orange { background: linear-gradient(135deg, #FF6B00 0%, #FFB347 100%); color: white; }
        .text-primary-custom { color: #4318FF !important; }
        .section-title { font-weight: 700; color: #2b3674; margin-bottom: 1.25rem; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 20px; background: linear-gradient(180deg, #4318FF, #868CFF); border-radius: 4px; }
        .timeline { border-left: 2px solid #E2E8F0; padding-left: 20px; position: relative; margin-left: 8px; }
        .timeline-item { position: relative; margin-bottom: 18px; }
        .timeline-icon { position: absolute; left: -28px; top: 3px; width: 14px; height: 14px; border-radius: 50%; background: linear-gradient(135deg, #4318FF, #868CFF); border: 3px solid #fff; box-shadow: 0 0 0 2px #c0c8ff; }
        .chart-container { position: relative; height: 250px; width: 100%; }
        .mini-overview-card { background: #f7f9ff; border-radius: 12px; padding: 14px 18px; border-left: 4px solid #4318FF; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; }
        .mini-overview-card:hover { background: #eef0ff; }
        .stat-row-label { font-size: 0.8rem; font-weight: 700; color: #A3AED0; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-row-val { font-size: 1.5rem; font-weight: 700; }
        .badge-soft-success { background: rgba(1,181,116,0.12); color: #01B574; }
        .badge-soft-danger { background: rgba(238,93,80,0.12); color: #EE5D50; }
        .badge-soft-warning { background: rgba(255,181,71,0.12); color: #FFB547; }
        .badge-soft-info { background: rgba(57,184,255,0.12); color: #39B8FF; }
        .badge-soft-primary { background: rgba(67,24,255,0.12); color: #4318FF; }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-white sidebar collapse" style="box-shadow:2px 0 8px rgba(0,0,0,0.04);">
            <?php include 'sidebar.php'; ?>
        </nav>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <?php include 'topbar.php'; ?>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                <div>
                    <h1 class="h4 fw-bold mb-0" style="color:#2b3674;">Dashboard Overview</h1>
                    <p class="text-muted small mb-0">Welcome back! Here is what is happening today.</p>
                </div>
            </div>
<?php if ($userRole === 'Admin'): ?>
<!-- ADMIN DASHBOARD -->
<!-- KPI Cards Row 1: Students, Staff, Admins -->
<div class="row mb-3 g-3">
    <!-- Students -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='view_students.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-primary shadow-sm"><i class="bi bi-people-fill"></i></div>
                <div><div class="stat-row-label">Total Students</div><div class="stat-row-val"><?= $count_total ?></div></div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <span class="badge badge-soft-success rounded-pill px-2"><?= $count_active ?> Active</span>
                <span class="badge badge-soft-danger rounded-pill px-2"><?= $count_inactive ?> Inactive</span>
            </div>
        </div>
    </div>
    <!-- Staff -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='view_staff.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-info shadow-sm"><i class="bi bi-person-vcard-fill"></i></div>
                <div><div class="stat-row-label">Total Staff</div><div class="stat-row-val"><?= $count_staff_total ?></div></div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <span class="badge badge-soft-success rounded-pill px-2"><?= $count_staff_active ?> Active</span>
                <span class="badge badge-soft-danger rounded-pill px-2"><?= $count_staff_inactive ?> Inactive</span>
            </div>
        </div>
    </div>
    <!-- Admins -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='view_admins.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-purple shadow-sm"><i class="bi bi-shield-fill-check"></i></div>
                <div><div class="stat-row-label">Total Admins</div><div class="stat-row-val"><?= $count_admins_total ?></div></div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <span class="badge badge-soft-success rounded-pill px-2"><?= $count_admins_active ?> Active</span>
                <span class="badge badge-soft-danger rounded-pill px-2"><?= $count_admins_inactive ?> Inactive</span>
            </div>
        </div>
    </div>
    <!-- Classes -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='view_classes.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-teal shadow-sm"><i class="bi bi-building-fill"></i></div>
                <div><div class="stat-row-label">Classes</div><div class="stat-row-val"><?= $count_classes_total ?></div></div>
            </div>
            <div class="mt-2">
                <span class="badge badge-soft-info rounded-pill px-2"><?= $count_subjects_total ?> Subjects</span>
            </div>
        </div>
    </div>
    <!-- Exams -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='exam_schedule.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-warning shadow-sm"><i class="bi bi-calendar-check-fill"></i></div>
                <div><div class="stat-row-label">Upcoming Exams</div><div class="stat-row-val"><?= $count_exams_upcoming ?></div></div>
            </div>
            <div class="mt-2">
                <span class="badge badge-soft-primary rounded-pill px-2"><?= $count_exams_published ?> Published</span>
            </div>
        </div>
    </div>
    <!-- Pending Approvals -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card card-custom p-3 h-100" onclick="window.location.href='user_approvals.php'" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-danger shadow-sm"><i class="bi bi-person-exclamation"></i></div>
                <div><div class="stat-row-label">Pending Approvals</div><div class="stat-row-val"><?= $count_pending_users ?></div></div>
            </div>
            <div class="mt-2">
                <span class="badge badge-soft-warning rounded-pill px-2">Awaiting Review</span>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Charts -->
<div class="row mb-4 g-4">
    <div class="col-lg-8">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Student Distribution by Grade</div>
            <div class="chart-container" style="height:280px;">
                <canvas id="gradeChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Student Distribution</div>
            <div class="chart-container" style="height:200px;">
                <canvas id="studentPieChart"></canvas>
            </div>
            <div class="mt-3 text-center small">
                <span class="me-3"><i class="bi bi-circle-fill text-success me-1"></i>Active</span>
                <span><i class="bi bi-circle-fill text-danger me-1"></i>Inactive</span>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-4">
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Attendance Overview</div>
            <div class="chart-container" style="height:200px;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Fee Collection</div>
            <div class="chart-container" style="height:180px;">
                <canvas id="feeChart"></canvas>
            </div>
            <div class="mt-3 text-center small">
                <span class="me-3"><i class="bi bi-circle-fill text-success me-1"></i>Collected</span>
                <span><i class="bi bi-circle-fill text-warning me-1"></i>Pending</span>
            </div>
        </div>
    </div>

    <!-- Quick Overview -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Quick Overview</div>
            <div class="mini-overview-card" style="border-left-color:#01B574;">
                <div><div class="fw-bold small">Today's Attendance</div><div class="text-muted" style="font-size:0.75rem;">Present ratio</div></div>
                <div class="fw-bold fs-4 text-success"><?= $attendance_percent ?>%</div>
            </div>
            <div class="mini-overview-card" style="border-left-color:#4318FF;">
                <div><div class="fw-bold small">Published Exams</div><div class="text-muted" style="font-size:0.75rem;">Total exams: <?= $count_exams_total ?></div></div>
                <div class="fw-bold fs-4 text-primary-custom"><?= $count_exams_published ?></div>
            </div>
            <div class="mini-overview-card" style="border-left-color:#FFB547;">
                <div><div class="fw-bold small">Unassigned Students</div><div class="text-muted" style="font-size:0.75rem;">No class assigned</div></div>
                <div class="fw-bold fs-4 text-warning"><?= $count_unassigned_students ?></div>
            </div>
            <div class="mini-overview-card" style="border-left-color:#EE5D50;">
                <div><div class="fw-bold small">Today's Notifications</div><div class="text-muted" style="font-size:0.75rem;">Sent today</div></div>
                <div class="fw-bold fs-4 text-danger"><?= $count_notifications_today ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Recent Activities -->
<div class="row mb-4 g-4">
    <div class="col-12">
        <div class="card card-custom p-4">
            <div class="section-title">Recent Activities</div>
            <div class="timeline">
                <?php if(empty($recent_activities)): ?>
                    <p class="text-muted">No recent activities to display.</p>
                <?php else: ?>
                    <?php foreach($recent_activities as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon"></div>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-bold mb-0 small"><?= htmlspecialchars($activity['action']) ?></h6>
                                <p class="text-muted mb-0" style="font-size:0.8rem;"><?= htmlspecialchars($activity['detail']) ?></p>
                            </div>
                            <span class="text-muted ms-3 text-nowrap" style="font-size:0.75rem;"><?= date('M d, H:i', strtotime($activity['date'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const gradeData = <?= json_encode($grade_distribution) ?>;
    const gradeLabels = gradeData.map(i => 'Grade ' + i.grade);
    const gradeCounts = gradeData.map(i => i.student_count);
    new Chart(document.getElementById('gradeChart'), {
        type: 'bar',
        data: { labels: gradeLabels, datasets: [{ label: 'Students', data: gradeCounts, backgroundColor: '#4318FF', borderRadius: 8, barThickness: 32 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f0f0f0' } }, x: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('studentPieChart'), {
        type: 'pie',
        data: { labels: ['Active', 'Inactive'], datasets: [{ data: [<?= $count_active ?>, <?= $count_inactive ?>], backgroundColor: ['#01B574','#EE5D50'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 10 } } } }
    });
    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: { labels: ['Present','Absent','Late','Half-Day'], datasets: [{ label: 'Count', data: [<?= $attendance_overview['Present'] ?>,<?= $attendance_overview['Absent'] ?>,<?= $attendance_overview['Late'] ?>,<?= $attendance_overview['Half-Day'] ?>], backgroundColor: ['#01B574','#EE5D50','#FFB547','#39B8FF'], borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('feeChart'), {
        type: 'doughnut',
        data: { labels: ['Collected','Pending'], datasets: [{ data: [<?= $fee_collected ?>,<?= $fee_pending ?>], backgroundColor: ['#01B574','#FFB547'], borderWidth: 0, hoverOffset: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '72%' }
    });
});
</script>
<?php elseif ($userRole === 'Staff'): ?>
<!-- STAFF DASHBOARD -->
<?php if (!empty($latest_published_exam)): ?>
<div class="alert alert-info border-0 card-custom mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
        <div class="kpi-icon grad-info flex-shrink-0"><i class="bi bi-calendar-event-fill"></i></div>
        <div>
            <h5 class="fw-bold mb-1">Exam Schedule Released!</h5>
            <p class="mb-2 small">Schedule for <strong><?= htmlspecialchars($latest_published_exam['exam_name']) ?></strong> starting <?= date('d M Y', strtotime($latest_published_exam['start_date'])) ?> is now available.</p>
            <a href="staff_exam_schedule.php" class="btn btn-primary btn-sm px-4 rounded-pill">View Schedule</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Staff KPI Cards -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-primary shadow-sm"><i class="bi bi-people-fill"></i></div>
                <div><div class="stat-row-label">My Students</div><div class="stat-row-val"><?= $staff_total_students ?></div></div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <span class="badge badge-soft-success rounded-pill px-2"><?= $staff_active_students ?> Active</span>
                <span class="badge badge-soft-danger rounded-pill px-2"><?= $staff_inactive_students ?> Inactive</span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-info shadow-sm"><i class="bi bi-easel-fill"></i></div>
                <div><div class="stat-row-label">Assigned Classes</div><div class="stat-row-val"><?= $count_assigned_classes ?></div></div>
            </div>
            <div class="mt-2"><span class="badge badge-soft-info rounded-pill px-2">Class Teacher</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-warning shadow-sm"><i class="bi bi-book-fill"></i></div>
                <div><div class="stat-row-label">Subjects Teaching</div><div class="stat-row-val"><?= $count_assigned_subjects ?></div></div>
            </div>
            <div class="mt-2"><span class="badge badge-soft-warning rounded-pill px-2">Teaching Duties</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon grad-danger shadow-sm"><i class="bi bi-bell-fill"></i></div>
                <div><div class="stat-row-label">Unread Notifications</div><div class="stat-row-val"><?= $count_unread_notifications ?></div></div>
            </div>
            <div class="mt-2"><span class="badge badge-soft-danger rounded-pill px-2">New Messages</span></div>
        </div>
    </div>
</div>

<!-- My Teaching & Charts -->
<div class="row mb-4 g-4">
    <div class="col-lg-6">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">My Teaching</div>
            <h6 class="fw-bold text-muted mb-2 small text-uppercase">Class Teacher Of</h6>
            <?php if(empty($class_teacher_of)): ?>
                <p class="text-muted small mb-3">Not assigned as a class teacher.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach($class_teacher_of as $c): ?>
                        <span class="badge grad-primary rounded-pill px-3 py-2 shadow-sm"><i class="bi bi-star-fill me-1 text-warning"></i><?= htmlspecialchars($c['class_name']) ?> (<?= htmlspecialchars($c['grade'].'-'.$c['section']) ?>)</span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h6 class="fw-bold text-muted mb-2 small text-uppercase">Assigned Subjects</h6>
            <?php if(empty($assigned_subjects_list)): ?>
                <p class="text-muted small">No subjects assigned.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach($assigned_subjects_list as $s): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-light py-2">
                            <span class="fw-semibold small"><?= htmlspecialchars($s['subject_name']) ?></span>
                            <span class="badge bg-light text-dark border small"><?= htmlspecialchars($s['class_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Student Distribution</div>
            <div class="chart-container" style="height:200px;"><canvas id="staffStudentChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Today's Attendance</div>
            <div class="chart-container" style="height:200px;"><canvas id="staffAttendanceChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Upcoming Exams & Activities -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Upcoming Exam Duties</div>
            <?php if(empty($upcoming_exam_duties)): ?>
                <div class="alert alert-light text-center border rounded-3">No upcoming exams for your classes.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small">
                        <thead class="table-light"><tr><th>Exam Name</th><th>Start</th><th>End</th></tr></thead>
                        <tbody>
                            <?php foreach($upcoming_exam_duties as $exam): ?>
                                <tr><td class="fw-semibold text-primary-custom"><?= htmlspecialchars($exam['exam_name']) ?></td><td><?= date('M d, Y', strtotime($exam['start_date'])) ?></td><td><?= date('M d, Y', strtotime($exam['end_date'])) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Recent Activities</div>
            <div class="timeline">
                <?php if(empty($recent_activities)): ?>
                    <p class="text-muted">No recent activities.</p>
                <?php else: ?>
                    <?php foreach($recent_activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon"></div>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-bold mb-0 small"><?= htmlspecialchars($activity['action']) ?></h6>
                                    <p class="text-muted mb-0" style="font-size:0.8rem;"><?= htmlspecialchars($activity['detail']) ?></p>
                                </div>
                                <span class="text-muted ms-3 text-nowrap" style="font-size:0.75rem;"><?= date('M d, H:i', strtotime($activity['date'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    new Chart(document.getElementById('staffStudentChart'), {
        type: 'pie',
        data: { labels: ['Active','Inactive'], datasets: [{ data: [<?= $staff_active_students ?>,<?= $staff_inactive_students ?>], backgroundColor: ['#01B574','#EE5D50'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 10, boxWidth: 10 } } } }
    });
    new Chart(document.getElementById('staffAttendanceChart'), {
        type: 'doughnut',
        data: { labels: ['Present','Absent','Late','Half-Day'], datasets: [{ data: [<?= $attendance_overview['Present'] ?>,<?= $attendance_overview['Absent'] ?>,<?= $attendance_overview['Late'] ?>,<?= $attendance_overview['Half-Day'] ?>], backgroundColor: ['#01B574','#EE5D50','#FFB547','#39B8FF'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });
});
</script>
<?php else:
    include 'student_dashboard.php';
endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_SESSION['show_notification_popup']) && $_SESSION['show_notification_popup']): ?>
<div class="modal fade" id="notificationPopupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content card-custom border-0 shadow">
      <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#4318FF,#868CFF);border-radius:16px 16px 0 0;">
        <h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>New Notifications</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="notificationPopupList">
        <div class="p-4 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div> Loading...</div>
      </div>
      <div class="modal-footer border-0 bg-light" style="border-radius:0 0 16px 16px;">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('notification_action.php?action=fetch_unread&ajax=1')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.count > 0) {
                const list = document.getElementById('notificationPopupList');
                list.innerHTML = '';
                const unread = data.notifications.filter(n => n.is_read == 0);
                unread.forEach(n => {
                    let sender = n.sender_role === 'Admin' ? 'Management' : n.sender_name;
                    list.innerHTML += `<a href="view_notification.php?id=${n.id}" class="text-decoration-none text-dark d-block p-3 border-bottom"><h6 class="mb-1">${n.title}</h6><p class="mb-0 small text-muted">From: ${sender} &bull; ${new Date(n.created_at).toLocaleDateString()}</p></a>`;
                });
                new bootstrap.Modal(document.getElementById('notificationPopupModal')).show();
            }
        });
});
<\/script>
<?php unset($_SESSION['show_notification_popup']); ?>
<?php endif; ?>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>

