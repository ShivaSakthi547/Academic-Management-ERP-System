<?php
// view_students.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Only Admin and Staff can view students
if ($userRole !== 'Admin' && $userRole !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

// Filters & Search logic
$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM students WHERE 1=1";
$params = [];

if ($filter === 'active') {
    $sql .= " AND deleted_at IS NULL";
} elseif ($filter === 'inactive') {
    $sql .= " AND deleted_at IS NOT NULL";
}

if (!empty($search)) {
    $sql .= " AND (student_name LIKE ? OR student_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
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
    <title>View Students - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Export Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .photo-thumbnail { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid #e9ecef; }
        .student-table th {
            font-weight: 600;
            background-color: #f1f3f7;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .student-table tbody tr {
            transition: background-color 0.15s ease;
        }
        .student-table tbody tr:hover {
            background-color: #f8fafc;
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
                <h1 class="h2">Students Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button onclick="exportToExcel('exportTable','students_data')" class="btn btn-sm btn-success" title="Download Excel">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                    </button>
                    <button onclick="exportToPDF('exportTable','students_data','Students List')" class="btn btn-sm btn-danger" title="Download PDF">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                    <?php if ($userRole === 'Admin'): ?>
                        <a href="student_form.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Add New Student
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            

            <!-- Search and Filter -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <div class="row align-items-center justify-content-between g-3">
                    <div class="col-md-6">
                        <form action="view_students.php" method="GET" class="d-flex align-items-center mb-0">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control py-2" placeholder="Search by Student ID or Name..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary px-4" type="submit">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="view_students.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Student List Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-list-columns-reverse text-primary me-2"></i>
                        <span><?php echo ucfirst($filter); ?> Students List</span>
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-light text-secondary ms-2 small">Search: "<?php echo htmlspecialchars($search); ?>"</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="exportTable" class="table table-hover student-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Photo</th>
                                    <th class="py-3">Student ID</th>
                                    <th class="py-3">Name</th>
                                    <th class="py-3">Grade</th>
                                    <th class="py-3">Email</th>
                                    <th class="py-3 pe-4 text-end" style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x display-6 mb-2 d-block text-secondary"></i>
                                            No student records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td class="ps-4 py-3"><img src="<?php echo htmlspecialchars($student['photo']); ?>" class="photo-thumbnail"></td>
                                            <td class="fw-semibold text-primary"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['grade']); ?></span></td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td class="pe-4 text-end">
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-info btn-sm me-1" title="View Details">
                                                    <i class="bi bi-eye-fill"></i> View
                                                </a>
                                                <a href="student_form.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-primary btn-sm me-1" title="Edit Student">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </a>
                                                <?php if ($userRole === 'Admin'): ?>
                                                    <?php if ($student['deleted_at'] === null): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="showDeleteConfirm(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($filter); ?>')" title="Deactivate Student">
                                                            <i class="bi bi-trash3-fill"></i> Delete
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="showRestoreConfirm(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($filter); ?>')" title="Activate Student">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                        </button>
                                                    <?php endif; ?>
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
<script>
    function showDeleteConfirm(id, filter) {
        if (confirm("Move this student to Inactive?")) {
            window.location.href = "action.php?action=delete&id=" + id + "&ref=" + encodeURIComponent(filter);
        }
    }
    function showRestoreConfirm(id, filter) {
        if (confirm("Restore this student?")) {
            window.location.href = "action.php?action=restore&id=" + id + "&ref=" + encodeURIComponent(filter);
        }
    }
</script>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Student Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="students">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">From Date (Reg)</label>
                        <input type="date" class="form-control form-control-sm" name="date_from">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">To Date (Reg)</label>
                        <input type="date" class="form-control form-control-sm" name="date_to">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Section</label>
                        <input type="text" class="form-control form-control-sm" name="section">
                    </div>
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 mt-2">
                        <label class="form-label mb-1">Gender</label>
                        <select class="form-select form-select-sm" name="gender">
                            <option value="">All</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
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
