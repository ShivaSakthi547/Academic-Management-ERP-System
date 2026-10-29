<?php
// student_marks.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

if ($userRole !== 'Admin' && $userRole !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

$student_id = intval($_GET['id'] ?? 0);
if ($student_id <= 0) {
    header("Location: marks.php");
    exit();
}

// Get Staff Allowed Subjects
$staff_id = null;
$allowed_subject_ids = [];
if ($userRole === 'Staff') {
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
    $stmt->execute([$userName]);
    $staff_id = $stmt->fetchColumn();
    if ($staff_id) {
        $stmt = $pdo->prepare("SELECT subject_id FROM subject_staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $allowed_subject_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}

// Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['save_marks', 'publish_marks'])) {
    $marks = $_POST['marks'] ?? [];
    $has_error = false;
    
    $pdo->beginTransaction();
    try {
        foreach ($marks as $subject_id => $mark) {
            $subject_id = intval($subject_id);
            if ($userRole === 'Staff' && !in_array($subject_id, $allowed_subject_ids)) {
                continue; // Not allowed to edit this subject
            }
            
            $mark_val = trim($mark);
            if ($mark_val === '') {
                $mark_val = null;
            } elseif (!is_numeric($mark_val) || $mark_val < 0 || $mark_val > 100) {
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Marks must be a number between 0 and 100.'];
                $has_error = true;
                break;
            }
            
            // Insert or Update
            if ($mark_val === null) {
                $stmt = $pdo->prepare("DELETE FROM student_marks WHERE student_id = ? AND subject_id = ?");
                $stmt->execute([$student_id, $subject_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO student_marks (student_id, subject_id, marks) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE marks = VALUES(marks)");
                $stmt->execute([$student_id, $subject_id, $mark_val]);
            }
        }
        
        if ($has_error) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
            
            if ($_POST['action'] === 'publish_marks') {
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                
                // Fetch student email
                $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
                $stmt_stu->execute([$student_id]);
                $stu_data = $stmt_stu->fetch();
                
                if ($stu_data && !empty($stu_data['email'])) {
                    $total_m = 0; $total_sub = 0;
                    foreach ($marks as $mark) {
                        if ($mark !== '') {
                            $total_m += floatval($mark);
                            $total_sub++;
                        }
                    }
                    $perc = $total_sub > 0 ? ($total_m / ($total_sub * 100)) * 100 : 0;
                    
                    $subject = "Marks Published";
                    $body = "<h3>Marks Published</h3>
                             <p>Dear {$stu_data['student_name']},</p>
                             <p>Your recent exam marks have been updated.</p>
                             <p><strong>Total Marks:</strong> $total_m</p>
                             <p><strong>Percentage:</strong> " . number_format($perc, 2) . "%</p>
                             <p><a href='http://localhost/student_registration/login.php'>Login here to view details</a></p>";
                    
                    $mailer->sendEmail($stu_data['email'], $subject, $body);
                }
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => $_POST['action'] === 'publish_marks' ? 'Marks saved and published.' : 'Marks updated successfully.'];
            header("Location: student_marks.php?id=" . $student_id);
            exit();
        }
    } catch (\Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Database error: ' . $e->getMessage()];
    }
}

// Fetch Student details and class
try {
    $stmt = $pdo->prepare("
        SELECT s.*, c.id as class_id, c.class_name, c.grade as class_grade, c.section 
        FROM students s
        LEFT JOIN class_students cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.id = ? AND s.deleted_at IS NULL
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student || !$student['class_id']) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Student not found or not assigned to a class.'];
        header("Location: marks.php");
        exit();
    }
    
    // Fetch Subjects and Marks
    $stmt = $pdo->prepare("
        SELECT sub.id as subject_id, sub.subject_name, sm.marks 
        FROM subjects sub
        LEFT JOIN student_marks sm ON sub.id = sm.subject_id AND sm.student_id = ?
        WHERE sub.class_id = ?
        ORDER BY sub.subject_name ASC
    ");
    $stmt->execute([$student_id, $student['class_id']]);
    $subjects = $stmt->fetchAll();
    
    // Fetch latest report card path
    $stmt_rep = $pdo->prepare("SELECT pdf_path FROM report_cards WHERE student_id = ? ORDER BY generated_at DESC LIMIT 1");
    $stmt_rep->execute([$student_id]);
    $latest_report = $stmt_rep->fetchColumn();
    
} catch (\PDOException $e) {
    die("Database fetch failed: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Marks - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .photo-thumbnail { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .mark-input { max-width: 120px; }
        .mark-input:read-only { background-color: #e9ecef; }
        .summary-box { background-color: #e0f2fe; border: 1px solid #bae6fd; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .summary-box h3 { color: #0369a1; margin-bottom: 0; font-weight: 700; }
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
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Marks</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                    <a href="marks.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to List
                    </a>
                </div>
            </div>

            

            <!-- Student Profile Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="Profile" class="photo-thumbnail me-4">
                        <div>
                            <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($student['student_name']); ?></h4>
                            <p class="text-muted mb-0">ID: <?php echo htmlspecialchars($student['student_id']); ?> | Grade: <?php echo htmlspecialchars($student['grade']); ?> | Section: <?php echo htmlspecialchars($student['section']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Marks Form -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-card-checklist text-primary me-2"></i>Subject Marks</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="student_marks.php?id=<?php echo $student_id; ?>" method="POST">
                                <input type="hidden" name="action" value="save_marks">
                                
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Subject</th>
                                            <th class="text-center">Marks (out of 100)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_marks = 0;
                                        $total_subjects = 0;
                                        $scored_subjects = 0;
                                        
                                        if (empty($subjects)): ?>
                                            <tr>
                                                <td colspan="2" class="text-center py-4 text-muted">No subjects assigned to this class yet.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($subjects as $sub): 
                                                $total_subjects++;
                                                if ($sub['marks'] !== null) {
                                                    $total_marks += floatval($sub['marks']);
                                                    $scored_subjects++;
                                                }
                                                
                                                $is_readonly = false;
                                                if ($userRole === 'Staff' && !in_array($sub['subject_id'], $allowed_subject_ids)) {
                                                    $is_readonly = true;
                                                }
                                        ?>
                                            <tr>
                                                <td class="fw-semibold text-dark">
                                                    <?php echo htmlspecialchars($sub['subject_name']); ?>
                                                    <?php if ($is_readonly): ?>
                                                        <span class="badge bg-light text-secondary border ms-2">Read-only</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control mark-input mx-auto text-center <?php echo $is_readonly ? 'bg-light' : ''; ?>" 
                                                        name="marks[<?php echo $sub['subject_id']; ?>]" 
                                                        value="<?php echo htmlspecialchars($sub['marks'] ?? ''); ?>"
                                                        <?php echo $is_readonly ? 'readonly' : ''; ?>
                                                        placeholder="N/A">
                                                </td>
                                            </tr>
                                        <?php 
                                            endforeach; 
                                        endif; 
                                        ?>
                                    </tbody>
                                </table>
                                
                                <div class="d-flex justify-content-end mt-4 gap-2">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Marks</button>
                                    <button type="submit" name="action" value="publish_marks" class="btn btn-info px-4 text-white"><i class="bi bi-envelope-check me-2"></i>Publish Marks</button>
                                    <a href="report_card_preview.php?id=<?php echo $student_id; ?>" class="btn btn-success px-4"><i class="bi bi-file-earmark-pdf me-2"></i>Generate Report Card</a>
                                    <?php if (!empty($latest_report)): ?>
                                        <a href="report_card_preview.php?id=<?php echo $student_id; ?>&auto_download=1" target="_blank" class="btn btn-outline-success px-4"><i class="bi bi-download me-2"></i>Download Report</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Marks Summary -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-calculator text-primary me-2"></i>Summary</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="summary-box mb-4">
                                <p class="text-muted text-uppercase small fw-bold mb-1">Total Marks</p>
                                <h3><?php echo $total_marks; ?> <span class="fs-6 text-secondary fw-normal">/ <?php echo ($total_subjects * 100); ?></span></h3>
                            </div>
                            
                            <?php 
                            $percentage = 0;
                            if ($total_subjects > 0) {
                                $percentage = ($total_marks / ($total_subjects * 100)) * 100;
                            }
                            ?>
                            <div class="summary-box bg-light border-light">
                                <p class="text-muted text-uppercase small fw-bold mb-1">Percentage</p>
                                <h3 class="text-dark"><?php echo number_format($percentage, 2); ?>%</h3>
                            </div>
                            
                            <hr class="my-4">
                            <ul class="list-unstyled mb-0 text-muted small">
                                <li class="mb-2"><i class="bi bi-info-circle me-2"></i>Marks are evaluated out of 100 per subject.</li>
                                <?php if ($userRole === 'Staff'): ?>
                                    <li><i class="bi bi-shield-lock me-2"></i>You can only edit marks for subjects assigned to you.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Marks Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="marks">
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Section</label>
                        <input type="text" class="form-control form-control-sm" name="section">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Student Name</label>
                        <input type="text" class="form-control form-control-sm" name="student">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">From Date</label>
                        <input type="date" class="form-control form-control-sm" name="date_from">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">To Date</label>
                        <input type="date" class="form-control form-control-sm" name="date_to">
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
