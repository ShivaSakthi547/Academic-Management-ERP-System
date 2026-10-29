<?php
// view_subjects.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Only Admin can view subjects
if ($userRole !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'active');

// Fetch classes with their statistics instead of subjects directly
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM class_students cs JOIN students st ON cs.student_id = st.id WHERE cs.class_id = c.id AND st.deleted_at IS NULL) as total_students,
        (SELECT COUNT(*) FROM subjects sub WHERE sub.class_id = c.id AND sub.deleted_at IS NULL) as total_subjects,
        s.staff_name as class_teacher_name
        FROM classes c 
        LEFT JOIN staff s ON c.class_teacher_id = s.id 
        WHERE 1=1";
$params = [];

if ($filter === 'active') {
    $sql .= " AND c.deleted_at IS NULL";
} elseif ($filter === 'inactive') {
    $sql .= " AND c.deleted_at IS NOT NULL";
}

if (!empty($search)) {
    $sql .= " AND c.class_name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.grade ASC, c.section ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();
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
    <title>Subject Management - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .class-card {
            border: none;
            border-radius: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            height: 100%;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            background: #fff;
            overflow: hidden;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }
        .class-card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .class-card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .class-card-body {
            padding: 1.5rem;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f1f3f5;
        }
        .stat-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .stat-value {
            font-weight: 600;
            color: #212529;
        }
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
                <h1 class="h2">Subject Management (Select Class)</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                </div>
            </div>

            

            <div class="card border-0 shadow-sm p-4 mb-4">
                <div class="row align-items-center justify-content-between g-3">
                    <div class="col-md-6">
                        <form action="view_subjects.php" method="GET" class="d-flex align-items-center mb-0">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control py-2" placeholder="Search by Class Name..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="view_subjects.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <?php if (empty($classes)): ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted fs-5">No classes found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <a href="class_details.php?id=<?php echo $class['id']; ?>" class="class-card">
                                <div class="class-card-header">
                                    <h3 class="class-card-title">Grade <?php echo htmlspecialchars($class['grade']); ?> - <?php echo htmlspecialchars($class['section']); ?></h3>
                                </div>
                                <div class="class-card-body">
                                    <div class="stat-row">
                                        <span class="stat-label"><i class="bi bi-people-fill me-2 text-info"></i>Students</span>
                                        <span class="stat-value badge bg-info text-dark rounded-pill"><?php echo $class['total_students']; ?></span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label"><i class="bi bi-book-half me-2 text-warning"></i>Subjects</span>
                                        <span class="stat-value badge bg-warning text-dark rounded-pill"><?php echo $class['total_subjects']; ?></span>
                                    </div>
                                    <div class="stat-row border-0 mb-0 pb-0 flex-column align-items-start mt-2 pt-2 border-top">
                                        <span class="stat-label mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Class Teacher</span>
                                        <span class="stat-value text-truncate w-100">
                                            <?php echo $class['class_teacher_name'] ? htmlspecialchars($class['class_teacher_name']) : '<span class="text-muted fw-normal fst-italic">Not Assigned</span>'; ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Subjects Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="subjects">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject">
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
