<?php
// view_classes.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Only Admin can view classes
if ($userRole !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'active');

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

$sql .= " ORDER BY c.created_at DESC";

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
    <title>View Classes - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Export Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .class-table th {
            font-weight: 600;
            background-color: #f1f3f7;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
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
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Classes Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button onclick="exportToExcel('exportTable','classes_data')" class="btn btn-sm btn-success" title="Download Excel">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                    </button>
                    <button onclick="exportToPDF('exportTable','classes_data','Classes List')" class="btn btn-sm btn-danger" title="Download PDF">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                    <a href="class_form.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Add New Class
                    </a>
                </div>
            </div>

            

            <div class="card border-0 shadow-sm p-4 mb-4">
                <div class="row align-items-center justify-content-between g-3">
                    <div class="col-md-6">
                        <form action="view_classes.php" method="GET" class="d-flex align-items-center mb-0">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control py-2" placeholder="Search by Class Name..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="view_classes.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'active' ? 'active fw-bold' : 'text-secondary'; ?>" href="view_classes.php?filter=active">Active Classes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'inactive' ? 'active fw-bold' : 'text-secondary'; ?>" href="view_classes.php?filter=inactive">Inactive Classes</a>
                </li>
            </ul>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="exportTable" class="table table-hover class-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Class Name</th>
                                    <th class="py-3">Grade</th>
                                    <th class="py-3">Students</th>
                                    <th class="py-3">Subjects</th>
                                    <th class="py-3">Class Teacher</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3 pe-4 text-end" style="width: 250px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">No classes found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-primary"><?php echo htmlspecialchars($class['class_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($class['grade']); ?></span></td>
                                            <td><span class="badge bg-info text-dark"><?php echo $class['total_students']; ?> Students</span></td>
                                            <td><span class="badge bg-warning text-dark"><?php echo $class['total_subjects']; ?> Subjects</span></td>
                                            <td><?php echo $class['class_teacher_name'] ? htmlspecialchars($class['class_teacher_name']) : '<span class="text-muted">None</span>'; ?></td>
                                            <td>
                                                <?php if ($class['deleted_at'] === null): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-info btn-sm me-1" title="View Details">
                                                    <i class="bi bi-eye-fill"></i> View
                                                </a>
                                                <a href="class_form.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-primary btn-sm me-1" title="Edit Class">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </a>
                                                <?php if ($class['deleted_at'] === null): ?>
                                                    <a href="action.php?action=delete_class&id=<?php echo $class['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Deactivate class?');">
                                                        <i class="bi bi-trash3-fill"></i> Delete
                                                    </a>
                                                <?php else: ?>
                                                    <a href="action.php?action=restore_class&id=<?php echo $class['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-success btn-sm" onclick="return confirm('Restore class?');">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/export_utils.js"></script>
<script src="includes/report_generator.js"></script>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Classes Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="classes">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Section</label>
                        <input type="text" class="form-control form-control-sm" name="section">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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
