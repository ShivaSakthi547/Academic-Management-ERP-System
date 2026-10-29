<?php
// view_admins.php
session_start();
require_once __DIR__ . 
'/config/db.php';

// Protect page: only authenticated users can access
if (!isset($_SESSION[
'user_id'])) {
    $_SESSION[
'toast'] = [
'type' => 
'danger', 
'msg' => 
'Unauthenticated access. Please log in.'
];
    header("Location: login.php");
    exit();
}

// Check if the user has Admin role
if ($_SESSION[
'user_role'] !== 
'Admin') {
    $_SESSION[
'toast'] = [
'type' => 
'danger', 
'msg' => 
'Access denied. Only administrators can view this page.'
];
    header("Location: dashboard.php"); // Redirect to a suitable page for non-admins
    exit();
}

$filter = trim($_GET['filter'] ?? 'active');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM admins WHERE 1=1";
$params = [];

if ($filter === 'active') {
    $sql .= " AND status = 1";
} elseif ($filter === 'inactive') {
    $sql .= " AND status = 0";
}

if (!empty($search)) {
    $sql .= " AND (admin_id LIKE ? OR admin_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database fetch failed: " . $e->getMessage());
}

$toast = $_SESSION[
'toast'] ?? null;
unset($_SESSION[
'toast']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Admins - Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Export Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: 
'Outfit', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .admin-table th {
            font-weight: 600;
            background-color: #f1f3f7;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .admin-table tbody tr {
            transition: background-color 0.15s ease;
        }
        .admin-table tbody tr:hover {
            background-color: #f8fafc;
        }
        .search-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .search-input {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .search-input:focus {
            z-index: 5;
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php include 'topbar.php'; ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                        <button onclick="exportToExcel('exportTable','admins_data')" class="btn btn-sm btn-success" title="Download Excel">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                        </button>
                        <button onclick="exportToPDF('exportTable','admins_data','Admins List')" class="btn btn-sm btn-danger" title="Download PDF">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                        </button>
                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                            <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                        </button>
                        <a href="admin_form.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Add New Admin
                        </a>
                    </div>
                </div>

                <?php if ($toast): ?>
                    <div class="alert alert-<?php echo $toast[
'type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($toast[
'msg']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card border-0 shadow-sm p-4 mb-4">
                    <div class="row align-items-center justify-content-between g-3">
                        <div class="col-md-6">
                            <form action="view_admins.php" method="GET" class="d-flex align-items-center mb-0">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control py-2 search-input" placeholder="Search by Admin ID or Name..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary px-4 search-btn" type="submit">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="view_admins.php" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Admins List Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                            <i class="bi bi-person-lines-fill text-primary me-2"></i>
                            <span>All Admins</span>
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-light text-secondary ms-2 small">Search: "<?php echo htmlspecialchars($search); ?>"</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="exportTable" class="table table-hover admin-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="py-3 ps-4">Admin ID</th>
                                        <th class="py-3">Admin Name</th>
                                        <th class="py-3">Email</th>
                                        <th class="py-3">Major Subject</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3 pe-4 text-end" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admins)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-person-x display-6 mb-2 d-block text-secondary"></i>
                                                No admin records found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td class="py-3 ps-4 fw-semibold text-primary">
                                                    <?php echo htmlspecialchars($admin['admin_id']); ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($admin['admin_name']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['major_subject']); ?></td>
                                                <td>
                                                    <?php if ($admin['status'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 pe-4 text-end">
                                                    <a href="view_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-outline-info btn-sm me-1" title="View Details">
                                                        <i class="bi bi-eye-fill"></i> View
                                                    </a>
                                                    <a href="admin_form.php?id=<?php echo $admin['id']; ?>" class="btn btn-outline-primary btn-sm me-1" title="Edit Admin">
                                                        <i class="bi bi-pencil-fill"></i> Edit
                                                    </a>
                                                    <?php if ($admin['status'] == 1): ?>
                                                        <a href="action.php?action=delete_admin&id=<?php echo $admin['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-danger btn-sm" title="Deactivate Admin" onclick="return confirm('Are you sure you want to deactivate this admin?');">
                                                            <i class="bi bi-trash3-fill"></i> Delete
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="action.php?action=restore_admin&id=<?php echo $admin['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-success btn-sm" title="Activate Admin" onclick="return confirm('Are you sure you want to activate this admin?');">
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Admin Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="admins">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Created Date</label>
                        <input type="date" class="form-control form-control-sm" name="created_date">
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