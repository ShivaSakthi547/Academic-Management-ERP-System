<?php
// attendance.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'Student') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access Denied.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$classes = [];
$total_students = 0;
$present_today = 0;
$absent_today = 0;
$monthly_percentage = 0;

$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

// Charts Data
$monthly_trend_labels = [];
$monthly_trend_data = [];

try {
    if ($userRole === 'Staff') {
        // Get staff ID
        $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
        $stmt_staff->execute([$userName]);
        $staff_id = $stmt_staff->fetchColumn();

        if ($staff_id) {
            // Get classes where staff is class teacher
            $stmt_classes = $pdo->prepare("SELECT id, class_name, grade, section FROM classes WHERE class_teacher_id = ? AND deleted_at IS NULL");
            $stmt_classes->execute([$staff_id]);
            $classes = $stmt_classes->fetchAll();
        }
    } elseif ($userRole === 'Admin') {
        $stmt_classes = $pdo->query("SELECT id, class_name, grade, section FROM classes WHERE deleted_at IS NULL");
        $classes = $stmt_classes->fetchAll();
    }

    if (!empty($classes)) {
        $class_ids = array_column($classes, 'id');
        $inQuery = implode(',', array_fill(0, count($class_ids), '?'));
        
        // Populate Total Students
        $stmt_students = $pdo->prepare("SELECT class_id, COUNT(*) as count FROM class_students WHERE class_id IN ($inQuery) GROUP BY class_id");
        $stmt_students->execute($class_ids);
        $student_counts = $stmt_students->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($classes as &$cls) {
            $cls['total_students'] = $student_counts[$cls['id']] ?? 0;
            $total_students += $cls['total_students'];
            
            // Present/Absent today for this class
            $stmt_today = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE class_id = ? AND attendance_date = ? GROUP BY status");
            $stmt_today->execute([$cls['id'], $today]);
            $today_stats = $stmt_today->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $cls['present_today'] = $today_stats['Present'] ?? 0;
            $cls['absent_today'] = $today_stats['Absent'] ?? 0;
            
            $present_today += $cls['present_today'];
            $absent_today += $cls['absent_today'];
        }
        unset($cls); // Prevent reference bug in subsequent loops

        // Monthly Attendance %
        $stmt_monthly = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM attendance 
            WHERE class_id IN ($inQuery) AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
            GROUP BY status
        ");
        $params = array_merge($class_ids, [$current_month, $current_year]);
        $stmt_monthly->execute($params);
        $monthly_stats = $stmt_monthly->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $total_monthly = ($monthly_stats['Present'] ?? 0) + ($monthly_stats['Absent'] ?? 0);
        if ($total_monthly > 0) {
            $monthly_percentage = round((($monthly_stats['Present'] ?? 0) / $total_monthly) * 100, 2);
        }

        // Monthly Trend Data (Last 6 months)
        for ($i = 5; $i >= 0; $i--) {
            $month_val = date('m', strtotime("-$i months"));
            $year_val = date('Y', strtotime("-$i months"));
            $month_name = date('M', strtotime("-$i months"));
            
            $monthly_trend_labels[] = $month_name;
            
            $stmt_trend = $pdo->prepare("
                SELECT status, COUNT(*) as count 
                FROM attendance 
                WHERE class_id IN ($inQuery) AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
                GROUP BY status
            ");
            $trend_params = array_merge($class_ids, [$month_val, $year_val]);
            $stmt_trend->execute($trend_params);
            $trend_stats = $stmt_trend->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $t_total = ($trend_stats['Present'] ?? 0) + ($trend_stats['Absent'] ?? 0);
            if ($t_total > 0) {
                $monthly_trend_data[] = round((($trend_stats['Present'] ?? 0) / $t_total) * 100, 2);
            } else {
                $monthly_trend_data[] = 0;
            }
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); transition: all 0.2s ease-in-out; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0, 40, 120, 0.1); }
        .class-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); transition: all 0.2s ease-in-out; cursor: pointer; border-left: 5px solid #0d6efd; text-decoration: none; display: block; color: inherit; }
        .class-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15); }
        .section-title { font-weight: 600; color: #343a40; margin-bottom: 20px; font-size: 1.25rem; }
        .chart-container { position: relative; height: 300px; width: 100%; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); }
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
                <h1 class="h2">Attendance Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                </div>
            </div>

            

            <!-- Analytics Section -->
            <h3 class="section-title"><i class="bi bi-graph-up text-primary me-2"></i>Analytics</h3>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card p-3" style="border-left: 5px solid #0dcaf0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><p class="text-secondary small fw-bold text-uppercase mb-1">Total Students</p><h2 class="fw-bold text-info mb-0"><?php echo $total_students; ?></h2></div>
                            <div class="fs-1 text-info"><i class="bi bi-people-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card p-3" style="border-left: 5px solid #198754;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><p class="text-secondary small fw-bold text-uppercase mb-1">Present Today</p><h2 class="fw-bold text-success mb-0"><?php echo $present_today; ?></h2></div>
                            <div class="fs-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card p-3" style="border-left: 5px solid #dc3545;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><p class="text-secondary small fw-bold text-uppercase mb-1">Absent Today</p><h2 class="fw-bold text-danger mb-0"><?php echo $absent_today; ?></h2></div>
                            <div class="fs-1 text-danger"><i class="bi bi-x-circle-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card p-3" style="border-left: 5px solid #6f42c1;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><p class="text-secondary small fw-bold text-uppercase mb-1">Monthly %</p><h2 class="fw-bold text-purple mb-0" style="color: #6f42c1;"><?php echo $monthly_percentage; ?>%</h2></div>
                            <div class="fs-1" style="color: #6f42c1;"><i class="bi bi-pie-chart-fill"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-5">
                <div class="col-md-6 mb-3">
                    <div class="chart-container">
                        <canvas id="todayBarChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="chart-container">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Classes Section -->
            <h3 class="section-title"><i class="bi bi-building text-primary me-2"></i><?php echo $userRole === 'Admin' ? 'All Classes' : 'My Assigned Classes'; ?></h3>
            <div class="row mb-5">
                <?php if (empty($classes)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No classes available.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $cls): ?>
                        <div class="col-md-4 mb-4">
                            <a href="attendance_sheet.php?class_id=<?php echo $cls['id']; ?>" class="card class-card p-4">
                                <h4 class="fw-bold mb-1">Grade -<?php echo htmlspecialchars($cls['grade']); ?> Sec- <?php echo htmlspecialchars($cls['section']); ?></h4>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($cls['class_name']); ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-secondary small fw-bold">Total Students</span>
                                    <span class="badge bg-secondary rounded-pill px-3"><?php echo $cls['total_students']; ?></span>
                                </div>
                                
                                <?php if ($userRole === 'Admin'): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-success small fw-bold">Present Today</span>
                                    <span class="badge bg-success rounded-pill px-3"><?php echo $cls['present_today']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-danger small fw-bold">Absent Today</span>
                                    <span class="badge bg-danger rounded-pill px-3"><?php echo $cls['absent_today']; ?></span>
                                </div>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Today's Attendance Bar Chart
    const ctxBar = document.getElementById('todayBarChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Present', 'Absent'],
            datasets: [{
                label: 'Today\'s Attendance',
                data: [<?php echo $present_today; ?>, <?php echo $absent_today; ?>],
                backgroundColor: [
                    'rgba(25, 135, 84, 0.7)', // Success
                    'rgba(220, 53, 69, 0.7)'  // Danger
                ],
                borderColor: [
                    'rgba(25, 135, 84, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Today\'s Overview' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Monthly Trend Line Chart
    const ctxLine = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_trend_labels); ?>,
            datasets: [{
                label: 'Attendance %',
                data: <?php echo json_encode($monthly_trend_data); ?>,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.2)', // Primary light
                borderColor: 'rgba(13, 110, 253, 1)',
                tension: 0.4,
                borderWidth: 2,
                pointBackgroundColor: 'rgba(13, 110, 253, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Monthly Attendance Trend (Last 6 Months)' }
            },
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
</script>
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Attendance Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="attendance">
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Section</label>
                        <input type="text" class="form-control form-control-sm" name="section">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Student</label>
                        <input type="text" class="form-control form-control-sm" name="student">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                        </select>
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
