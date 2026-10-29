<?php
// hall_allotment.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$exam_id = $_GET['exam_id'] ?? ($_POST['exam_id'] ?? null);

if (!$exam_id) {
    header("Location: exam_schedule.php");
    exit();
}

// Fetch exam details and class info
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name, c.grade, c.section 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        die("Exam not found.");
    }

    $class_id = $exam['class_id'];

    // Fetch active halls
    $stmt_halls = $pdo->query("SELECT * FROM exam_halls WHERE status = 1 ORDER BY hall_name");
    $all_halls = $stmt_halls->fetchAll(PDO::FETCH_ASSOC);

    // Fetch students for this class ordered by student_id
    $stmt_students = $pdo->prepare("
        SELECT s.id as student_id, s.student_id as roll_no, s.student_name 
        FROM students s 
        JOIN class_students cs ON s.id = cs.student_id 
        WHERE cs.class_id = ? AND s.deleted_at IS NULL
        ORDER BY s.student_id ASC
    ");
    $stmt_students->execute([$class_id]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    $total_students = count($students);

    // Fetch existing allotments
    $stmt_existing = $pdo->prepare("
        SELECT ha.*, eh.hall_name, eh.hall_code 
        FROM hall_allotments ha
        JOIN exam_halls eh ON ha.hall_id = eh.id
        WHERE ha.exam_id = ?
    ");
    $stmt_existing->execute([$exam_id]);
    $existing_allotments = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map for quick lookup
    $allotment_map = [];
    foreach ($existing_allotments as $ea) {
        $allotment_map[$ea['student_id']] = $ea;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$action = $_POST['action'] ?? '';
$preview_data = null;
$selected_halls_ids = $_POST['selected_halls'] ?? [];
$insufficient_capacity = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'preview' || $action === 'regenerate') {
        // Run allotment logic
        if (empty($selected_halls_ids)) {
            $_SESSION['toast'] = ['type' => 'warning', 'msg' => 'Please select at least one hall.'];
        } else {
            // Get selected halls details
            $selected_halls = [];
            $total_capacity = 0;
            foreach ($all_halls as $h) {
                if (in_array($h['id'], $selected_halls_ids)) {
                    $selected_halls[] = $h;
                    $total_capacity += $h['capacity'];
                }
            }

            if ($total_capacity < $total_students) {
                $insufficient_capacity = true;
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => "Insufficient capacity! Selected halls total $total_capacity seats, but there are $total_students students."];
            } else {
                // Allotment logic
                $preview_data = [];
                $hall_index = 0;
                $current_hall_allotted = 0;

                foreach ($students as $stu) {
                    // Check if current hall is full
                    if ($current_hall_allotted >= $selected_halls[$hall_index]['capacity']) {
                        $hall_index++;
                        $current_hall_allotted = 0;
                    }

                    $preview_data[] = [
                        'student_id' => $stu['student_id'],
                        'roll_no' => $stu['roll_no'],
                        'student_name' => $stu['student_name'],
                        'hall_id' => $selected_halls[$hall_index]['id'],
                        'hall_name' => $selected_halls[$hall_index]['hall_name'],
                        'hall_code' => $selected_halls[$hall_index]['hall_code'],
                    ];
                    $current_hall_allotted++;
                }
            }
        }
    } elseif ($action === 'save') {
        $preview_student_ids = $_POST['preview_student_id'] ?? [];
        $preview_hall_ids = $_POST['preview_hall_id'] ?? [];

        if (!empty($preview_student_ids)) {
            try {
                $pdo->beginTransaction();
                
                // Delete old allotments
                $stmt_del = $pdo->prepare("DELETE FROM hall_allotments WHERE exam_id = ?");
                $stmt_del->execute([$exam_id]);

                // Insert new allotments
                $stmt_ins = $pdo->prepare("INSERT INTO hall_allotments (exam_id, student_id, hall_id) VALUES (?, ?, ?)");
                foreach ($preview_student_ids as $index => $s_id) {
                    $h_id = $preview_hall_ids[$index];
                    $stmt_ins->execute([$exam_id, $s_id, $h_id]);
                }

                $pdo->commit();
                
                // Send Emails for Hall Ticket
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                
                $stmt_exam = $pdo->prepare("SELECT exam_name FROM exams WHERE id = ?");
                $stmt_exam->execute([$exam_id]);
                $exam_name = $stmt_exam->fetchColumn();
                
                $placeholders = implode(',', array_fill(0, count($preview_student_ids), '?'));
                $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id IN ($placeholders) AND email IS NOT NULL AND email != ''");
                $stmt_stu->execute($preview_student_ids);
                $students_email = $stmt_stu->fetchAll();
                
                $subject = "Hall Ticket Available: $exam_name";
                foreach ($students_email as $stu) {
                    $body = "<h3>Hall Ticket Available</h3>
                             <p>Dear {$stu['student_name']} / Parent,</p>
                             <p>Your Hall Ticket for <strong>$exam_name</strong> has been generated and is now available.</p>
                             <p>Please log in to the student portal to view and download your Hall Ticket.</p>";
                    $mailer->sendEmail($stu['email'], $subject, $body);
                }
                
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Hall Allotment Generated Successfully!'];
                header("Location: hall_allotment.php?exam_id=$exam_id");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to save allotment: ' . $e->getMessage()];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall Allotment - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
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
                <div>
                    <h1 class="h2"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Hall Allotment</h1>
                    <p class="text-muted mb-0">For <?php echo htmlspecialchars($exam['exam_name']); ?> - <?php echo htmlspecialchars($exam['class_name'] . ' (' . $exam['grade'] . '-' . $exam['section'] . ')'); ?></p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                    <a href="class_exam_details.php?class_id=<?php echo $class_id; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Exams</a>
                </div>
            </div>

            <div class="row">
                <!-- Hall Selection Panel -->
                <div class="col-md-4 mb-4">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3 border-bottom pb-2">Allotment Configuration</h5>
                        <p class="small text-muted">Select available halls to distribute <strong><?php echo $total_students; ?></strong> students.</p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="preview">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            
                            <div class="mb-4" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($all_halls as $hall): ?>
                                    <div class="form-check border p-2 mb-2 rounded bg-light">
                                        <input class="form-check-input ms-1 me-2" type="checkbox" name="selected_halls[]" value="<?php echo $hall['id']; ?>" id="hall<?php echo $hall['id']; ?>" <?php echo in_array($hall['id'], $selected_halls_ids) ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100 d-flex justify-content-between" for="hall<?php echo $hall['id']; ?>">
                                            <span class="fw-bold"><?php echo htmlspecialchars($hall['hall_name']); ?> (<?php echo htmlspecialchars($hall['hall_code']); ?>)</span>
                                            <span class="badge bg-secondary">Cap: <?php echo $hall['capacity']; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-magic me-1"></i> Generate Preview</button>
                        </form>
                    </div>
                </div>

                <!-- Preview / Current Allotment Panel -->
                <div class="col-md-8 mb-4">
                    <div class="card p-4">
                        <?php if ($preview_data !== null): ?>
                            <!-- Preview Mode -->
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h5 class="fw-bold text-warning mb-0"><i class="bi bi-eye-fill me-2"></i>Preview Hall Allotment</h5>
                                <div>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="regenerate">
                                        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                        <?php foreach ($selected_halls_ids as $shid): ?>
                                            <input type="hidden" name="selected_halls[]" value="<?php echo $shid; ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-clockwise me-1"></i> Regenerate</button>
                                    </form>
                                    <a href="hall_allotment.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-secondary me-2">Cancel</a>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                
                                <div class="table-responsive" style="max-height: 500px;">
                                    <table class="table table-hover table-striped align-middle">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Student Name</th>
                                                <th>Class</th>
                                                <th>Hall Number</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preview_data as $pd): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pd['roll_no']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($pd['student_name']); ?>
                                                        <input type="hidden" name="preview_student_id[]" value="<?php echo $pd['student_id']; ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($exam['grade'] . '-' . $exam['section']); ?></td>
                                                    <td>
                                                        <select class="form-select form-select-sm" name="preview_hall_id[]" required>
                                                            <?php foreach ($selected_halls as $h): ?>
                                                                <option value="<?php echo $h['id']; ?>" <?php echo $h['id'] == $pd['hall_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($h['hall_name'] . ' (' . $h['hall_code'] . ')'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-save-fill me-2"></i> Save Hall Allotment</button>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- Current Allotment View -->
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h5 class="fw-bold text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>Current Hall Allotment</h5>
                            </div>
                            
                            <?php if (empty($students)): ?>
                                <div class="alert alert-warning">No students found in this class.</div>
                            <?php elseif (empty($existing_allotments)): ?>
                                <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                                    <div class="fs-1 text-secondary mb-3"><i class="bi bi-diagram-3"></i></div>
                                    <h5>No hall allotment generated yet.</h5>
                                    <p class="text-muted">Select halls from the left panel and click Generate Preview.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 500px;">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Student Name</th>
                                                <th>Hall Name</th>
                                                <th>Hall Code</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $stu): ?>
                                                <?php $allotment = $allotment_map[$stu['student_id']] ?? null; ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stu['roll_no']); ?></td>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($stu['student_name']); ?></td>
                                                    <?php if ($allotment): ?>
                                                        <td class="text-primary"><i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($allotment['hall_name']); ?></td>
                                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($allotment['hall_code']); ?></span></td>
                                                    <?php else: ?>
                                                        <td colspan="2" class="text-danger">Not Allotted</td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Hall Allotment Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="hall_allotment">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Section</label>
                        <input type="text" class="form-control form-control-sm" name="section">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Hall Name</label>
                        <input type="text" class="form-control form-control-sm" name="hall_name">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Student</label>
                        <input type="text" class="form-control form-control-sm" name="student">
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
