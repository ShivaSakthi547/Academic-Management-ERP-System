<?php
// exam_schedule.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

try {
    // Fetch all active classes
    $stmt = $pdo->query("SELECT id, class_name, grade, section FROM classes WHERE status = 1 AND deleted_at IS NULL ORDER BY grade, section");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare statistics per class
    foreach ($classes as &$cls) {
        $class_id = $cls['id'];

        // Total Students
        $stmt_stu = $pdo->prepare("SELECT COUNT(*) FROM class_students WHERE class_id = ?");
        $stmt_stu->execute([$class_id]);
        $cls['total_students'] = $stmt_stu->fetchColumn();

        // Total Subjects
        $stmt_sub = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE class_id = ? AND deleted_at IS NULL");
        $stmt_sub->execute([$class_id]);
        $cls['total_subjects'] = $stmt_sub->fetchColumn();

        // Active Exam Status
        $stmt_exam = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE class_id = ? AND status = 'Active'");
        $stmt_exam->execute([$class_id]);
        $active_exams = $stmt_exam->fetchColumn();
        $cls['active_exams'] = $active_exams;
    }
    unset($cls); // Fix reference bug
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedule - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .class-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); transition: all 0.2s ease-in-out; cursor: pointer; height: 100%; border-top: 5px solid #0d6efd;}
        .class-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15); }
        .stat-badge { background: #e9ecef; padding: 5px 10px; border-radius: 8px; font-weight: 600; color: #495057; font-size: 0.85rem;}
        .active-exam-badge { background-color: #e0f8e9; color: #198754; }
        .no-exam-badge { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
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
                <h1 class="h2"><i class="bi bi-calendar-event me-2 text-primary"></i>Exam Schedule Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                    <a href="exam_reports.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-bar-chart-fill me-1"></i> View Analytics
                    </a>
                </div>
            </div>

            <p class="text-muted mb-4">Select a class to manage its exam schedules, timetables, and hall allotments.</p>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php if (empty($classes)): ?>
                    <div class="col-12">
                        <div class="alert alert-warning">No active classes found. Please add classes first.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $cls): ?>
                        <div class="col">
                            <div class="card class-card p-4" onclick="window.location.href='class_exam_details.php?class_id=<?php echo $cls['id']; ?>'">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($cls['class_name']); ?></h4>
                                        <p class="text-secondary small mb-0">Grade <?php echo htmlspecialchars($cls['grade']); ?> • Section <?php echo htmlspecialchars($cls['section']); ?></p>
                                    </div>
                                    <div class="fs-2 text-primary opacity-50"><i class="bi bi-building"></i></div>
                                </div>
                                
                                <div class="d-flex gap-2 mb-3 mt-2">
                                    <span class="stat-badge"><i class="bi bi-people-fill me-1 text-primary"></i> <?php echo $cls['total_students']; ?> Students</span>
                                    <span class="stat-badge"><i class="bi bi-book-half me-1 text-info"></i> <?php echo $cls['total_subjects']; ?> Subjects</span>
                                </div>

                                <div class="mt-auto pt-3 border-top">
                                    <?php if ($cls['active_exams'] > 0): ?>
                                        <span class="badge active-exam-badge rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> <?php echo $cls['active_exams']; ?> Active Exam(s)</span>
                                    <?php else: ?>
                                        <span class="badge no-exam-badge rounded-pill px-3 py-2"><i class="bi bi-info-circle-fill me-1"></i> No Active Exams</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="includes/export_utils.js"></script>
<script src="includes/report_generator.js"></script>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Exam Schedule Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="exam_schedule">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Date</label>
                        <input type="date" class="form-control form-control-sm" name="date">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Exam Type</label>
                        <select class="form-select form-select-sm" name="exam_type">
                            <option value="">All</option>
                            <option value="Midterm">Midterm</option>
                            <option value="Final">Final</option>
                            <option value="Unit Test">Unit Test</option>
                        </select>
                    </div>
                    <div class="col-12 text-end mt-3">
                        <button type="button" class="btn btn-sm btn-secondary me-2" onclick="document.getElementById('reportFilterForm').reset(); document.getElementById('reportTableContainer').innerHTML=''; document.getElementById('reportTableBody').innerHTML='<tr><td class=\'text-center text-muted\' colspan=\'100%\'>Apply filters and click Generate Report</td></tr>'; document.getElementById('btnReportExcel').disabled=true; document.getElementById('btnReportPdf').disabled=true;">Reset Filters</button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="generateReport()">Generate Report</button>
                    </div>
                </form>

                <div class="table-responsive mb-3" style="max-height: 400px; overflow-y: auto;" id="reportTableContainer">
                    <table class="table table-bordered table-hover mb-0" id="reportTable">
                        <thead class="table-light" id="reportTableHead"></thead>
                        <tbody id="reportTableBody">
                            <tr><td class="text-center text-muted" colspan="100%">Apply filters and click Generate Report</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-start gap-2">
                    <button onclick="exportReportToPDF()" class="btn btn-sm btn-danger" title="Download PDF" id="btnReportPdf" disabled>
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                    </button>
                    <button onclick="exportReportToExcel()" class="btn btn-sm btn-success" title="Download Excel" id="btnReportExcel" disabled>
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
