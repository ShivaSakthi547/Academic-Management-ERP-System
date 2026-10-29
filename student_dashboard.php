<?php
// student_dashboard.php

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

$student_username = $_SESSION['user_name']; // username is student_id

try {
    // Find student info
    $stmt_stu = $pdo->prepare("
        SELECT s.id, s.student_name, s.photo, s.student_id, c.grade, c.section, c.class_teacher_id, c.id AS class_id
        FROM students s
        LEFT JOIN class_students cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.student_id = ?
    ");
    $stmt_stu->execute([$student_username]);
    $student = $stmt_stu->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "<div class='alert alert-danger'>Student profile not found.</div>";
        exit;
    }

    $class_id = $student['class_id'];
    $grade = $student['grade'];
    $section = $student['section'];

    // Find class teacher
    $class_teacher = null;
    if ($student['class_teacher_id']) {
        $stmt_ct = $pdo->prepare("SELECT staff_name, phone_number, email FROM staff WHERE id = ?");
        $stmt_ct->execute([$student['class_teacher_id']]);
        $class_teacher = $stmt_ct->fetch(PDO::FETCH_ASSOC);
    }

    // Find subject teachers
    $subject_teachers = [];
    if ($class_id) {
        $stmt_st = $pdo->prepare("
            SELECT sub.subject_name, st.staff_name, st.phone_number
            FROM subjects sub
            JOIN subject_staff ss ON sub.id = ss.subject_id
            JOIN staff st ON ss.staff_id = st.id
            WHERE sub.class_id = ?
        ");
        $stmt_st->execute([$class_id]);
        $subject_teachers = $stmt_st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate Rank
    $rank = null;
    if ($grade && $section) {
        $stmt_rank = $pdo->prepare("
            SELECT s.id AS stu_id, IFNULL(SUM(sm.marks), 0) AS total_marks
            FROM students s
            JOIN class_students cs ON s.id = cs.student_id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN student_marks sm ON s.id = sm.student_id
            WHERE c.grade = ? AND c.section = ?
            GROUP BY s.id
            ORDER BY total_marks DESC
        ");
        $stmt_rank->execute([$grade, $section]);
        $ranked_students = $stmt_rank->fetchAll(PDO::FETCH_ASSOC);
        
        $current_rank = 1;
        foreach ($ranked_students as $rs) {
            if ($rs['stu_id'] == $student['id']) {
                $rank = $current_rank;
                break;
            }
            $current_rank++;
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$photo = !empty($student['photo']) ? $student['photo'] : 'uploads/default_avatar.png';

// Check for published exams for this class
$latest_published_exam = null;
if ($class_id) {
    try {
        $stmt_exam = $pdo->prepare("SELECT * FROM exams WHERE class_id = ? AND is_published = 1 AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 1");
        $stmt_exam->execute([$class_id]);
        $latest_published_exam = $stmt_exam->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error silently
    }
}
?>

<?php if (!empty($latest_published_exam)): ?>
<div class="alert alert-info border-0 card-custom mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
        <div class="kpi-icon grad-info flex-shrink-0"><i class="bi bi-calendar-event-fill"></i></div>
        <div>
            <h5 class="fw-bold mb-1">Exam Schedule Released!</h5>
            <p class="mb-2 small">The schedule for <strong><?= htmlspecialchars($latest_published_exam['exam_name']) ?></strong> (<?= htmlspecialchars($latest_published_exam['exam_type']) ?>) starting on <?= date('d M Y', strtotime($latest_published_exam['start_date'])) ?> is now available.</p>
            <a href="student_exam_schedule.php" class="btn btn-primary btn-sm px-4 rounded-pill">View Exam Schedule</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Welcome Banner -->
<div class="card card-custom mb-4 p-0 overflow-hidden" style="background:linear-gradient(135deg,#4318FF 0%,#868CFF 70%,#39B8FF 100%);">
    <div class="p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-4">
            <img src="<?= htmlspecialchars($photo) ?>" alt="Profile" class="rounded-circle shadow-sm" style="width:72px;height:72px;object-fit:cover;border:3px solid rgba(255,255,255,0.5);">
            <div>
                <div class="text-white opacity-75 small fw-semibold text-uppercase">Student Portal</div>
                <h3 class="fw-bold text-white mb-1">Welcome, <?= htmlspecialchars($student['student_name']) ?>!</h3>
                <div class="text-white opacity-75 small">ID: <?= htmlspecialchars($student['student_id']) ?> &bull; Grade <?= htmlspecialchars($grade ?? 'N/A') ?> &bull; Section <?= htmlspecialchars($section ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($rank === 1): ?>
                <span class="badge rounded-pill px-3 py-2 fw-bold fs-6 shadow" style="background:linear-gradient(135deg,#FFD700,#FDB931);color:#333;">🏆 #1 Rank Holder</span>
            <?php elseif ($rank === 2): ?>
                <span class="badge rounded-pill px-3 py-2 fw-bold fs-6 shadow" style="background:linear-gradient(135deg,#C0C0C0,#A9A9A9);">🥈 #2 Rank</span>
            <?php elseif ($rank === 3): ?>
                <span class="badge rounded-pill px-3 py-2 fw-bold fs-6 shadow text-white" style="background:linear-gradient(135deg,#CD7F32,#A0522D);">🥉 #3 Rank</span>
            <?php elseif ($rank !== null): ?>
                <span class="badge badge-soft-primary rounded-pill px-3 py-2 fw-bold fs-6">Rank #<?= $rank ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Info Cards Row -->
<div class="row mb-4 g-3">
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 text-center h-100">
            <div class="kpi-icon grad-primary mx-auto mb-2"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="stat-row-label text-center">Grade</div>
            <div class="fw-bold fs-3 text-primary-custom"><?= htmlspecialchars($grade ?? 'N/A') ?></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 text-center h-100">
            <div class="kpi-icon grad-info mx-auto mb-2"><i class="bi bi-diagram-3-fill"></i></div>
            <div class="stat-row-label text-center">Section</div>
            <div class="fw-bold fs-3 text-primary-custom"><?= htmlspecialchars($section ?? 'N/A') ?></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 text-center h-100">
            <div class="kpi-icon grad-warning mx-auto mb-2"><i class="bi bi-book-fill"></i></div>
            <div class="stat-row-label text-center">Subjects</div>
            <div class="fw-bold fs-3 text-primary-custom"><?= count($subject_teachers) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 text-center h-100">
            <div class="kpi-icon grad-success mx-auto mb-2"><i class="bi bi-award-fill"></i></div>
            <div class="stat-row-label text-center">Class Rank</div>
            <div class="fw-bold fs-3 text-primary-custom"><?= $rank !== null ? '#'.$rank : 'N/A' ?></div>
        </div>
    </div>
</div>

<!-- Class Teacher & Subjects -->
<div class="row g-4">
    <!-- Class Teacher Card -->
    <div class="col-md-4">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Class Teacher</div>
            <?php if ($class_teacher): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="kpi-icon grad-primary"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($class_teacher['staff_name']) ?></h6>
                        <div class="text-muted small">Your Class Teacher</div>
                    </div>
                </div>
                <div class="bg-light rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2 text-muted small">
                        <i class="bi bi-telephone-fill text-primary-custom"></i>
                        <span><?= htmlspecialchars($class_teacher['phone_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="bi bi-envelope-fill text-primary-custom"></i>
                        <span><?= htmlspecialchars($class_teacher['email'] ?? 'N/A') ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center rounded-3">
                    <i class="bi bi-person-x display-6 text-muted d-block mb-2"></i>
                    No class teacher assigned.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Subject Teachers Card -->
    <div class="col-md-8">
        <div class="card card-custom p-4 h-100">
            <div class="section-title">Subject Teachers</div>
            <?php if (!empty($subject_teachers)): ?>
                <div class="row g-3">
                    <?php foreach ($subject_teachers as $st): ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 hover-lift" style="transition:border-color 0.2s;border-color:#E2E8F0!important;">
                                <div class="fw-bold text-primary-custom mb-1 small"><?= htmlspecialchars($st['subject_name']) ?></div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:28px;height:28px;font-size:0.7rem;font-weight:700;flex-shrink:0;"><?= strtoupper(substr($st['staff_name'],0,2)) ?></div>
                                    <div>
                                        <div class="fw-semibold small"><?= htmlspecialchars($st['staff_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($st['phone_number'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center rounded-3">
                    <i class="bi bi-book display-6 text-muted d-block mb-2"></i>
                    No subjects or teachers assigned.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
