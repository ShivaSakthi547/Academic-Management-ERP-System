<?php
// fee_history.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$search = trim($_GET['search'] ?? '');
$grade_filter = trim($_GET['grade'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query dynamically
$query = "
    SELECT fp.*, sf.total_fee, sf.status as fee_status, s.student_name, s.student_id, s.grade 
    FROM fee_payments fp 
    JOIN student_fees sf ON fp.student_fee_id = sf.id 
    JOIN students s ON sf.student_id = s.id 
    WHERE s.deleted_at IS NULL
";
$params = [];

if ($search) {
    $query .= " AND (s.student_name LIKE ? OR s.student_id = ? OR fp.receipt_no = ?)";
    $params[] = "%$search%";
    $params[] = $search;
    $params[] = $search;
}

if ($grade_filter) {
    $query .= " AND s.grade = ?";
    $params[] = $grade_filter;
}

if ($date_from) {
    $query .= " AND fp.payment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND fp.payment_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY fp.payment_date DESC, fp.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch grades for filter
    $stmt_grades = $pdo->query("SELECT DISTINCT grade FROM fee_structures ORDER BY FIELD(grade, 'Pre-KG', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12')");
    $grades = $stmt_grades->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payment History - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
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
                <h1 class="h2">Payment History</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#reportModal" title="Generate Report">
                        <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Search</label>
                            <input type="text" class="form-control form-control-sm" name="search" placeholder="Name, ID or Receipt" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Grade</label>
                            <select class="form-select form-select-sm" name="grade">
                                <option value="">All Grades</option>
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $g === $grade_filter ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">From Date</label>
                            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">To Date</label>
                            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="fee_history.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Receipt No</th>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Grade</th>
                                    <th>Method</th>
                                    <th>Amount Paid</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No payments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $pay): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($pay['receipt_no']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($pay['student_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($pay['student_id']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($pay['grade']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($pay['payment_method']); ?></span></td>
                                            <td class="fw-bold text-success">₹<?php echo number_format($pay['amount_paid'], 2); ?></td>
                                            <td class="text-end pe-4">
                                                <a href="fee_receipt.php?payment_id=<?php echo $pay['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-file-earmark-pdf"></i> View Receipt
                                                </a>
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
                <h5 class="modal-title"><i class="bi bi-bar-chart-fill me-2"></i>Generate Fees Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportFilterForm" class="row g-3 mb-4 card flex-row p-3 shadow-sm mx-0">
                    <input type="hidden" name="module" value="fees">
                    <div class="col-md-2 mt-0">
                        <label class="form-label mb-1">Grade</label>
                        <input type="text" class="form-control form-control-sm" name="grade">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Student</label>
                        <input type="text" class="form-control form-control-sm" name="student">
                    </div>
                    <div class="col-md-3 mt-0">
                        <label class="form-label mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All</option>
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
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
