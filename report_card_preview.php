<?php
// report_card_preview.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$student_id = intval($_GET['id'] ?? 0);
if ($student_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

if ($userRole === 'Student') {
    $stmt_check = $pdo->prepare("SELECT student_id FROM students WHERE id = ?");
    $stmt_check->execute([$student_id]);
    $db_student_id = $stmt_check->fetchColumn();
    
    if ($db_student_id !== $userName) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
        header("Location: student_dashboard.php");
        exit();
    }
} elseif ($userRole !== 'Admin' && $userRole !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

// Fetch student info
$stmt = $pdo->prepare("
    SELECT s.*, c.id as class_id, c.class_name, c.grade as class_grade, c.section, c.class_teacher_id,
           stf.staff_name as class_teacher_name
    FROM students s
    LEFT JOIN class_students cs ON s.id = cs.student_id
    LEFT JOIN classes c ON cs.class_id = c.id
    LEFT JOIN staff stf ON c.class_teacher_id = stf.id
    WHERE s.id = ? AND s.deleted_at IS NULL
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student || !$student['class_id']) {
    die("Student not found or not assigned to a class.");
}

// Fetch institution info
$inst = [];
try {
    $stmt_inst = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $inst = $stmt_inst->fetch();
} catch (\PDOException $e) {}

$school_name = $inst['school_name'] ?? 'SMART CAMPUS MATRIC HIGHER SECONDARY SCHOOL';
$inst_code = $inst['institution_code'] ?? 'SCMHSS-2000';
$school_address = $inst['address'] ?? '123 Education Street, Knowledge City';
$school_phone = $inst['phone'] ?? '+1 234 567 8900';
$school_email = $inst['email'] ?? 'info@smartcampus.edu';
$school_logo = !empty($inst['logo_path']) ? $inst['logo_path'] : 'uploads/default_logo.png';
$academic_year = '2026-2027';

// Fetch Subjects and Marks
$stmt_marks = $pdo->prepare("
    SELECT sub.subject_name, sm.marks 
    FROM subjects sub
    JOIN student_marks sm ON sub.id = sm.subject_id AND sm.student_id = ?
    WHERE sub.class_id = ?
    ORDER BY sub.subject_name ASC
");
$stmt_marks->execute([$student_id, $student['class_id']]);
$marks_data = $stmt_marks->fetchAll();

$total_marks = 0;
$total_subjects = 0;
$chart_labels = [];
$chart_data = [];

foreach ($marks_data as $m) {
    if ($m['marks'] !== null) {
        $total_marks += floatval($m['marks']);
        $total_subjects++;
        $chart_labels[] = $m['subject_name'];
        $chart_data[] = floatval($m['marks']);
    }
}

$max_marks = $total_subjects * 100;
$percentage = $total_subjects > 0 ? ($total_marks / $max_marks) * 100 : 0;

$performance_level = "Needs Improvement";
if ($percentage >= 90) $performance_level = "Excellent";
elseif ($percentage >= 75) $performance_level = "Very Good";
elseif ($percentage >= 60) $performance_level = "Good";
elseif ($percentage >= 40) $performance_level = "Average";

// Fetch Attendance Summary for Report Card
$stmt_att = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance 
    WHERE student_id = ?
");
$stmt_att->execute([$student['id']]);
$att = $stmt_att->fetch(PDO::FETCH_ASSOC);

$total_days = $att['total_days'] ?? 0;
$present_days = $att['present_days'] ?? 0;
$absent_days = $att['absent_days'] ?? 0;
$att_percent = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;

// Calculate Rank
$stmt_rank = $pdo->prepare("
    SELECT sm.student_id, SUM(sm.marks) as total 
    FROM student_marks sm
    JOIN class_students cs ON sm.student_id = cs.student_id
    WHERE cs.class_id = ?
    GROUP BY sm.student_id
    ORDER BY total DESC
");
$stmt_rank->execute([$student['class_id']]);
$rank_list = $stmt_rank->fetchAll();

$rank = '-';
$current_rank = 1;
foreach ($rank_list as $r) {
    if ($r['student_id'] == $student['id']) {
        $rank = $current_rank;
        break;
    }
    $current_rank++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Use specific version of html2pdf that works reliably -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f0f2f5; }
        #report-card-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 20mm;
            border-radius: 10px;
        }
        .header-section { border-bottom: 3px solid #0d6efd; padding-bottom: 20px; margin-bottom: 20px; }
        .logo-img { max-height: 80px; }
        .school-title { color: #0d6efd; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .section-title { background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 5px 10px; font-weight: 600; margin-bottom: 15px; }
        .details-table th { background: #f8f9fa; width: 30%; }
        .marks-table th { background: #0d6efd; color: white; }
        .marks-table td, .marks-table th { text-align: center; vertical-align: middle; }
        .marks-table td:first-child, .marks-table th:first-child { text-align: left; }
        .summary-box { border: 2px solid #0d6efd; border-radius: 10px; padding: 15px; text-align: center; }
        .summary-box h4 { color: #0d6efd; font-weight: 700; margin-bottom: 5px; }
        .chart-container { position: relative; height: 300px; width: 100%; margin-top: 20px; }
        
        @media print {
            body { background: white; }
            #report-card-container { margin: 0; box-shadow: none; border-radius: 0; padding: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container py-4 no-print text-center">
    <button class="btn btn-secondary me-2" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button>
    <button class="btn btn-primary" id="btn-save-pdf"><i class="bi bi-file-earmark-pdf me-2"></i>Save & Download PDF</button>
    <?php if ($userRole === 'Student'): ?>
        <a href="student_marks_view.php" class="btn btn-outline-dark ms-2"><i class="bi bi-arrow-left me-2"></i>Back</a>
    <?php else: ?>
        <a href="student_marks.php?id=<?php echo $student_id; ?>" class="btn btn-outline-dark ms-2"><i class="bi bi-arrow-left me-2"></i>Back</a>
    <?php endif; ?>
</div>

<div id="report-card-container">
    <div id="report-card-content">
        <!-- School Header -->
        <div class="header-section text-center">
            <div class="row align-items-center">
                <div class="col-2">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" class="logo-img" alt="Logo" onerror="this.src='uploads/default_logo.png'">
                </div>
                <div class="col-10 text-start">
                    <h2 class="school-title mb-1"><?php echo htmlspecialchars($school_name); ?></h2>
                    <p class="mb-0 text-muted small">
                        <strong>Institution Code:</strong> <?php echo htmlspecialchars($inst_code); ?> | 
                        <strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?>
                    </p>
                    <p class="mb-0 text-muted small">
                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($school_address); ?> | 
                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($school_phone); ?> | 
                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($school_email); ?>
                    </p>
                </div>
            </div>
        </div>

        <h3 class="text-center fw-bold mb-4">STUDENT REPORT CARD</h3>

        <!-- Student Details Section -->
        <div class="section-title">Student Details</div>
        <table class="table table-bordered details-table mb-4">
            <tr>
                <th>Student Name</th>
                <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                <th>Student ID</th>
                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
            </tr>
            <tr>
                <th>Grade & Section</th>
                <td><?php echo htmlspecialchars($student['grade'] . ' - ' . $student['section']); ?></td>
                <th>Class Teacher</th>
                <td><?php echo htmlspecialchars($student['class_teacher_name'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Date of Birth</th>
                <td><?php echo htmlspecialchars(date('d-M-Y', strtotime($student['date_of_birth']))); ?></td>
                <th>Report Date</th>
                <td><?php echo date('d-M-Y'); ?></td>
            </tr>
        </table>

        <!-- Subject Marks Table -->
        <div class="section-title">Academic Performance</div>
        <table class="table table-bordered table-striped marks-table mb-4">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Maximum Marks</th>
                    <th>Marks Obtained</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marks_data as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['subject_name']); ?></td>
                    <td>100</td>
                    <td><strong><?php echo htmlspecialchars($m['marks'] ?? 'N/A'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td>TOTAL</td>
                    <td><?php echo $max_marks; ?></td>
                    <td><?php echo $total_marks; ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Attendance Summary Section -->
        <div class="section-title">Attendance Summary</div>
        <table class="table table-bordered mb-4 text-center">
            <thead class="table-light">
                <tr>
                    <th>Total Working Days</th>
                    <th>Days Present</th>
                    <th>Days Absent</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo $total_days; ?></strong></td>
                    <td class="text-success"><strong><?php echo $present_days; ?></strong></td>
                    <td class="text-danger"><strong><?php echo $absent_days; ?></strong></td>
                    <td class="text-primary"><strong><?php echo $att_percent; ?>%</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals & Performance Summary Section -->
        <div class="row mb-5 align-items-center">
            <div class="col-md-6">
                <table class="table table-bordered mb-0">
                    <tr><th class="bg-light w-50">Total Marks</th><td><strong><?php echo $total_marks; ?> / <?php echo $max_marks; ?></strong></td></tr>
                    <tr><th class="bg-light w-50">Percentage</th><td><strong><?php echo number_format($percentage, 2); ?>%</strong></td></tr>
                    <tr><th class="bg-light w-50">Class Rank</th><td><strong><?php echo $rank; ?></strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="summary-box h-100">
                    <p class="mb-1 text-muted text-uppercase small fw-bold">Performance Level</p>
                    <h4><?php echo $performance_level; ?></h4>
                    <p class="mb-0 small text-muted mt-2">
                        90%+: Excellent | 75%-89%: Very Good | 60%-74%: Good<br>
                        40%-59%: Average | Below 40%: Needs Improvement
                    </p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="section-title">Performance Analytics</div>
        <div class="row">
            <div class="col-md-7">
                <p class="text-center fw-bold small text-muted mb-0">Subject Wise Performance</p>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
            <div class="col-md-5">
                <p class="text-center fw-bold small text-muted mb-0">Marks Distribution</p>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="mt-5 pt-4 text-center text-muted small" style="border-top: 1px dashed #ccc;">
            <p>This is a system generated report card and does not require a physical signature.</p>
        </div>

    </div>
</div>

<script>
    // Initialize Charts
    const labels = <?php echo json_encode($chart_labels); ?>;
    const data = <?php echo json_encode($chart_data); ?>;
    
    // Bar Chart
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Marks Obtained',
                data: data,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            },
            animation: {
                duration: 0 // Disable animation for instantaneous rendering for PDF
            }
        }
    });

    // Pie Chart
    const pieColors = [
        '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
    ];
    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: pieColors.slice(0, data.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 0
            }
        }
    });

    // Handle Save & Download PDF
    document.getElementById('btn-save-pdf').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
        
        const element = document.getElementById('report-card-container');
        const opt = {
            margin:       0,
            filename:     '<?php echo $student['student_id']; ?>_ReportCard.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        // First generate PDF for download directly
        var worker = html2pdf().set(opt).from(element);
        worker.save().then(function() {
            // Then get the base64 string to save on server
            worker.outputPdf('datauristring').then(function(pdfBase64) {
                // Send to server
                fetch('save_report_card.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'student_id=<?php echo $student_id; ?>&class_id=<?php echo $student['class_id']; ?>&pdf_data=' + encodeURIComponent(pdfBase64)
                })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Save & Download PDF';
                    if (data.success) {
                        alert('Report Card successfully saved to database!');
                    } else {
                        alert('Downloaded, but failed to save to database: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Save & Download PDF';
                });
            });
        }).catch(err => {
            console.error('PDF Generation Error:', err);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Save & Download PDF';
            alert('Failed to generate PDF. Please try again.');
        });
    });

    <?php if (isset($_GET['auto_download']) && $_GET['auto_download'] == '1'): ?>
    window.addEventListener('load', function() {
        // Slight delay to ensure charts are fully rendered
        setTimeout(function() {
            document.getElementById('btn-save-pdf').click();
        }, 500);
    });
    <?php endif; ?>
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
