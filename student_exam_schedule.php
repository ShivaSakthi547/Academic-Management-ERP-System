<?php
// student_exam_schedule.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$student_username = $_SESSION['user_name']; // student_id

try {
    // Get student's internal ID and class ID
    $stmt_stu = $pdo->prepare("
        SELECT s.id, cs.class_id 
        FROM students s 
        JOIN class_students cs ON s.id = cs.student_id 
        WHERE s.student_id = ?
    ");
    $stmt_stu->execute([$student_username]);
    $student = $stmt_stu->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['class_id']) {
        die("Student profile or class mapping not found.");
    }

    $internal_student_id = $student['id'];
    $class_id = $student['class_id'];

    // Fetch published exams for this class
    $stmt_exams = $pdo->prepare("
        SELECT e.*, c.class_name, c.grade, c.section 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        WHERE e.class_id = ? AND e.is_published = 1 
        ORDER BY e.start_date DESC
    ");
    $stmt_exams->execute([$class_id]);
    $published_exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

    foreach ($published_exams as &$exam) {
        $exam_id = $exam['id'];
        
        // Fetch timetable
        $stmt_tt = $pdo->prepare("
            SELECT et.*, s.subject_name 
            FROM exam_timetable et 
            JOIN subjects s ON et.subject_id = s.id 
            WHERE et.exam_id = ?
            ORDER BY et.exam_date ASC, et.start_time ASC
        ");
        $stmt_tt->execute([$exam_id]);
        $exam['timetable'] = $stmt_tt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch student's own hall allotment
        $stmt_ha = $pdo->prepare("
            SELECT ha.*, eh.hall_name, eh.hall_code 
            FROM hall_allotments ha 
            JOIN exam_halls eh ON ha.hall_id = eh.id 
            WHERE ha.exam_id = ? AND ha.student_id = ?
        ");
        $stmt_ha->execute([$exam_id, $internal_student_id]);
        $exam['my_allotment'] = $stmt_ha->fetch(PDO::FETCH_ASSOC);
    }
    unset($exam); // Fix reference bug
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exam Schedule - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .exam-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .exam-header { background-color: #fff; padding: 20px; border-bottom: 1px solid #eee; border-radius: 12px 12px 0 0; border-left: 5px solid #0dcaf0; }
        .exam-body { padding: 20px; background-color: #fff; border-radius: 0 0 12px 12px; }
        .hall-info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;}
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <?php include 'sidebar.php'; ?>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <?php include 'topbar.php'; ?>
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2"><i class="bi bi-calendar-check me-2 text-primary"></i>My Exam Schedule</h1>
            </div>

            <?php if (empty($published_exams)): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="fs-1 text-secondary mb-3"><i class="bi bi-calendar-x"></i></div>
                    <h5>No exams scheduled for your class at the moment.</h5>
                </div>
            <?php else: ?>
                <?php foreach ($published_exams as $exam): ?>
                    <div class="card exam-card">
                        <div class="exam-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($exam['exam_type']); ?></span>
                                <span class="text-muted small"><i class="bi bi-calendar me-1"></i> <?php echo date('d M Y', strtotime($exam['start_date'])) . ' to ' . date('d M Y', strtotime($exam['end_date'])); ?></span>
                            </div>
                            <div>
                                <a href="exam_schedule_pdf.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-outline-danger btn-sm mb-1"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Schedule PDF</a>
                                <?php if ($exam['my_allotment']): ?>
                                    <a href="hall_ticket.php?exam_id=<?php echo $exam['id']; ?>&student_id=<?php echo $internal_student_id; ?>" class="btn btn-primary btn-sm mb-1" target="_blank"><i class="bi bi-person-vcard-fill me-1"></i> Hall Ticket</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="exam-body">
                            
                            <div class="row">
                                <div class="col-lg-8 mb-4 mb-lg-0">
                                    <!-- Timetable Section -->
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-clock-history text-primary me-2"></i>Subject Timetable</h5>
                                    <?php if (empty($exam['timetable'])): ?>
                                        <p class="text-muted small">No timetable available yet.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Subject</th>
                                                        <th>Date</th>
                                                        <th>Start Time</th>
                                                        <th>End Time</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($exam['timetable'] as $tt): ?>
                                                        <tr>
                                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($tt['subject_name']); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($tt['exam_date'])); ?></td>
                                                            <td><?php echo date('h:i A', strtotime($tt['start_time'])); ?></td>
                                                            <td><?php echo date('h:i A', strtotime($tt['end_time'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-lg-4">
                                    <!-- Hall Allotment Section -->
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-building text-info me-2"></i>My Hall Allotment</h5>
                                    
                                    <div class="hall-info-card p-4 text-center h-100">
                                        <?php if ($exam['my_allotment']): ?>
                                            <div class="fs-1 text-primary opacity-50 mb-2"><i class="bi bi-door-open-fill"></i></div>
                                            <p class="text-muted small text-uppercase fw-bold mb-1">Assigned Hall</p>
                                            <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($exam['my_allotment']['hall_name']); ?></h4>
                                            <h5 class="text-primary mb-0 border-top pt-2 mt-2">Code: <?php echo htmlspecialchars($exam['my_allotment']['hall_code']); ?></h5>
                                        <?php else: ?>
                                            <div class="fs-1 text-secondary opacity-50 mb-2"><i class="bi bi-exclamation-circle-fill"></i></div>
                                            <h5 class="fw-bold text-muted mt-3">Not Allotted Yet</h5>
                                            <p class="small text-muted mb-0">Your hall allotment for this exam has not been finalized.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
