<?php
// view_staff.php
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
if ($_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can view this page.'];
    header("Location: dashboard.php"); // Redirect to a suitable page for non-admins
    exit();
}

// ---------------------------------------------------------
// SINGLE STAFF PROFILE VIEW
// ---------------------------------------------------------
$staffId = intval($_GET['id'] ?? 0);

if ($staffId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$s) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Record not found.'];
            header("Location: view_staff.php");
            exit();
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }

    try {
        $stmt_as = $pdo->prepare("SELECT s.subject_name, c.class_name, c.grade 
                                  FROM subject_staff ss 
                                  JOIN subjects s ON ss.subject_id = s.id 
                                  JOIN classes c ON s.class_id = c.id 
                                  WHERE ss.staff_id = ?");
        $stmt_as->execute([$staffId]);
        $assigned_subjects = $stmt_as->fetchAll();
        
        $stmt_ct = $pdo->prepare("SELECT class_name, grade FROM classes WHERE class_teacher_id = ?");
        $stmt_ct->execute([$staffId]);
        $assigned_classes_rows = $stmt_ct->fetchAll();
        $assigned_classes = [];
        foreach ($assigned_classes_rows as $row) {
            $assigned_classes[] = $row['class_name'] . ' (' . $row['grade'] . ')';
        }
    } catch (PDOException $e) {
        $assigned_subjects = [];
        $assigned_classes = [];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Staff - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .profile-img-container {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            border-radius: 50%;
            padding: 5px;
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.2);
        }
        .staff-profile-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            border-radius: 50%; 
            border: 4px solid #fff; 
        }
        .profile-card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); 
            overflow: hidden;
            background: #fff;
        }
        .profile-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 40px 20px 20px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); }
        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 1.1rem;
            color: #212529;
            font-weight: 500;
            margin-bottom: 20px;
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Staff Details</h1>
                <div>
                    <a href="view_staff.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to List</a>
                    <a href="staff_form.php?id=<?php echo $s['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-fill"></i> Edit Profile</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card profile-card">
                        <div class="profile-header">
                            <div class="profile-img-container mb-3">
                                <img src="<?php echo htmlspecialchars($s['photo']); ?>" alt="Profile" class="staff-profile-img" onerror="this.src='uploads/default_avatar.png'">
                            </div>
                            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($s['staff_name']); ?></h3>
                            <p class="text-secondary mb-2">ID: <?php echo htmlspecialchars($s['staff_id']); ?></p>
                            <span class="badge <?php echo ($s['status'] == 1) ? 'bg-success' : 'bg-danger'; ?> fs-6 rounded-pill px-3 py-2">
                                <?php echo ($s['status'] == 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card detail-card p-4">
                        <h5 class="border-bottom pb-3 mb-4 fw-bold text-primary"><i class="bi bi-person-badge-fill me-2"></i>Staff Information</h5>
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-person me-1"></i> Staff Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-hash me-1"></i> Staff ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($s['staff_id']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-calendar-date me-1"></i> Date of Birth</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($s['dob'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-shield-lock me-1"></i> Role</div>
                                <div class="info-value"><span class="badge bg-primary fs-6">Staff</span></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-house-door me-1"></i> Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($s['address'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-envelope me-1"></i> Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($s['email']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-telephone me-1"></i> Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($s['phone_number']); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-book me-1"></i> Major Subject</div>
                                <div class="info-value"><?php echo htmlspecialchars($s['major_subject']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-clock-history me-1"></i> Created Date</div>
                                <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($s['created_at'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-activity me-1"></i> Status</div>
                                <div class="info-value">
                                    <?php if ($s['status'] == 1): ?>
                                        <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card detail-card p-4 mt-4">
                        <h5 class="border-bottom pb-3 mb-4 fw-bold text-success"><i class="bi bi-journal-check me-2"></i>Assignments</h5>
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-building me-1"></i> Assigned Classes</div>
                                <div class="info-value">
                                    <?php if (empty($assigned_classes)): ?>
                                        <span class="text-muted fst-italic">None assigned</span>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($assigned_classes as $cls): ?>
                                                <li><i class="bi bi-dot"></i> <?php echo htmlspecialchars($cls); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-book-half me-1"></i> Assigned Subjects</div>
                                <div class="info-value">
                                    <?php if (empty($assigned_subjects)): ?>
                                        <span class="text-muted fst-italic">None assigned</span>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($assigned_subjects as $sub): ?>
                                                <li><i class="bi bi-dot"></i> <?php echo htmlspecialchars($sub['subject_name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($sub['class_name']); ?>)</small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
    <?php
    exit(); // Stop execution here so we don't render the list
}
// ---------------------------------------------------------
// LIST VIEW
// ---------------------------------------------------------

$filter = trim($_GET['filter'] ?? 'active');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM staff WHERE 1=1";
$params = [];

if ($filter === 'active') {
    $sql .= " AND status = 1";
} elseif ($filter === 'inactive') {
    $sql .= " AND status = 0";
}

if (!empty($search)) {
    $sql .= " AND (staff_id LIKE ? OR staff_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Staff - Academic Management System</title>
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
        .staff-table th {
            font-weight: 600;
            background-color: #f1f3f7;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .staff-table tbody tr {
            transition: background-color 0.15s ease;
        }
        .staff-table tbody tr:hover {
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
                    <h1 class="h2">Staff Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                        <button onclick="exportToExcel('exportTable','staff_data')" class="btn btn-sm btn-success" title="Download Excel">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                        </button>
                        <button onclick="exportToPDF('exportTable','staff_data','Staff List')" class="btn btn-sm btn-danger" title="Download PDF">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                        </button>
                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                            <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                        </button>
                        <a href="staff_form.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Add New Staff
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
                            <form action="view_staff.php" method="GET" class="d-flex align-items-center mb-0">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control py-2 search-input" placeholder="Search by Staff ID or Name..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary px-4 search-btn" type="submit">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="view_staff.php" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Staff List Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                            <i class="bi bi-person-lines-fill text-primary me-2"></i>
                            <span>All Staff</span>
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-light text-secondary ms-2 small">Search: "<?php echo htmlspecialchars($search); ?>"</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="exportTable" class="table table-hover staff-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="py-3 ps-4">Staff ID</th>
                                        <th class="py-3">Staff Name</th>
                                        <th class="py-3">Email</th>
                                        <th class="py-3">Major Subject</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3 pe-4 text-end" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($staff)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-person-x display-6 mb-2 d-block text-secondary"></i>
                                                No staff records found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($staff as $s): ?>
                                            <tr>
                                                <td class="py-3 ps-4 fw-semibold text-primary">
                                                    <?php echo htmlspecialchars($s['staff_id']); ?>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                                <td><?php echo htmlspecialchars($s['major_subject']); ?></td>
                                                <td>
                                                    <?php if ($s['status'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 pe-4 text-end">
                                                    <a href="view_staff.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-info btn-sm me-1" title="View Details">
                                                        <i class="bi bi-eye-fill"></i> View
                                                    </a>
                                                    <a href="staff_form.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm me-1" title="Edit Staff">
                                                        <i class="bi bi-pencil-fill"></i> Edit
                                                    </a>
                                                    <?php if ($s['status'] == 1): ?>
                                                        <a href="action.php?action=delete_staff&id=<?php echo $s['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-danger btn-sm" title="Deactivate Staff" onclick="return confirm('Are you sure you want to deactivate this staff member?');">
                                                            <i class="bi bi-trash3-fill"></i> Delete
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="action.php?action=restore_staff&id=<?php echo $s['id']; ?>&ref=<?php echo urlencode($filter); ?>" class="btn btn-outline-success btn-sm" title="Activate Staff" onclick="return confirm('Are you sure you want to activate this staff member?');">
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

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/export_utils.js"></script>
<script src="includes/report_generator.js"></script>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Staff Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="staff">
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Joining Date From</label>
                        <input type="date" class="form-control form-control-sm" name="join_from">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Joining Date To</label>
                        <input type="date" class="form-control form-control-sm" name="join_to">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Major Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject">
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

<script>
    function showDeleteConfirm(id) {
        if (confirm("Are you sure you want to delete this staff member?")) {
            window.location.href = "action.php?action=delete_staff&id=" + id;
        }
    }
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>