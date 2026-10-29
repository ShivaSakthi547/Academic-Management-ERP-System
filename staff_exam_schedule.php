<?php
// staff_exam_schedule.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

try {
    // Fetch all published exams
    $stmt = $pdo->query("
        SELECT e.*, c.class_name, c.grade, c.section 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        WHERE e.is_published = 1 
        ORDER BY e.start_date DESC
    ");
    $published_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        // Fetch hall allotment
        $stmt_ha = $pdo->prepare("
            SELECT ha.*, s.student_id as roll_no, s.student_name, eh.hall_name, eh.hall_code 
            FROM hall_allotments ha 
            JOIN students s ON ha.student_id = s.id 
            JOIN exam_halls eh ON ha.hall_id = eh.id 
            WHERE ha.exam_id = ?
            ORDER BY eh.hall_name, s.student_id
        ");
        $stmt_ha->execute([$exam_id]);
        $exam['allotment'] = $stmt_ha->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Exam Schedules - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .exam-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .exam-header { background-color: #fff; padding: 20px; border-bottom: 1px solid #eee; border-radius: 12px 12px 0 0; border-left: 5px solid #0d6efd; }
        .exam-body { padding: 20px; background-color: #fff; border-radius: 0 0 12px 12px; }
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
                <h1 class="h2"><i class="bi bi-calendar-check me-2 text-primary"></i>Published Exam Schedules</h1>
            </div>

            <?php if (empty($published_exams)): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="fs-1 text-secondary mb-3"><i class="bi bi-journal-x"></i></div>
                    <h5>No exam schedules have been published yet.</h5>
                </div>
            <?php else: ?>
                <?php foreach ($published_exams as $exam): ?>
                    <div class="card exam-card">
                        <div class="exam-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($exam['exam_type']); ?></span>
                                <span class="badge bg-info text-dark me-2">Class: <?php echo htmlspecialchars($exam['class_name'] . ' (' . $exam['grade'] . '-' . $exam['section'] . ')'); ?></span>
                                <span class="text-muted small"><i class="bi bi-calendar me-1"></i> <?php echo date('d M Y', strtotime($exam['start_date'])) . ' to ' . date('d M Y', strtotime($exam['end_date'])); ?></span>
                            </div>
                            <div>
                                <a href="exam_schedule_pdf.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF</a>
                            </div>
                        </div>
                        <div class="exam-body">
                            <!-- Timetable Section -->
                            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-clock-history text-primary me-2"></i>Timetable</h5>
                            <?php if (empty($exam['timetable'])): ?>
                                <p class="text-muted small">No timetable generated.</p>
                            <?php else: ?>
                                <div class="table-responsive mb-4">
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

                            <!-- Hall Allotment Section -->
                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4"><i class="bi bi-building text-info me-2"></i>Hall Allotment</h5>
                            <?php if (empty($exam['allotment'])): ?>
                                <p class="text-muted small">No hall allotment generated yet.</p>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover table-sm align-middle">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Student Name</th>
                                                <th>Hall Name</th>
                                                <th>Hall Code</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($exam['allotment'] as $al): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($al['roll_no']); ?></td>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($al['student_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($al['hall_name']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($al['hall_code']); ?></span></td>
                                                    <td>
                                                        <a href="hall_ticket.php?exam_id=<?php echo $exam['id']; ?>&student_id=<?php echo $al['student_id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-printer me-1"></i> Hall Ticket</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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
