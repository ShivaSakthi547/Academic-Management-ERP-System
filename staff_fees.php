<?php
// staff_fees.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$search_query = trim($_GET['search'] ?? '');
$student_data = null;
$fee_data = null;
$installments = [];

if ($search_query) {
    // Search for student
    $stmt = $pdo->prepare("
        SELECT s.*, c.section, c.class_name 
        FROM students s 
        LEFT JOIN class_students cs ON s.id = cs.student_id 
        LEFT JOIN classes c ON cs.class_id = c.id 
        WHERE s.deleted_at IS NULL AND (s.student_id = ? OR s.student_name LIKE ?)
        LIMIT 1
    ");
    $stmt->execute([$search_query, "%$search_query%"]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student_data) {
        // Fetch fee data
        $stmt_fee = $pdo->prepare("SELECT * FROM student_fees WHERE student_id = ?");
        $stmt_fee->execute([$student_data['id']]);
        $fee_data = $stmt_fee->fetch(PDO::FETCH_ASSOC);

        if ($fee_data) {
            // Fetch past payments
            $stmt_payments = $pdo->prepare("SELECT * FROM fee_payments WHERE student_fee_id = ? ORDER BY payment_date DESC, created_at DESC");
            $stmt_payments->execute([$fee_data['id']]);
            $installments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Fees View - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .student-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; }
        .summary-card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
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
                <h1 class="h2">Student Fees</h1>
            </div>

            <!-- Search Section -->
            <div class="card summary-card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Enter Student ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                            <button class="btn btn-primary" type="submit">Search Student</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($search_query): ?>
                <?php if (!$student_data): ?>
                    <div class="alert alert-warning text-center">No student found matching '<?php echo htmlspecialchars($search_query); ?>'.</div>
                <?php else: ?>
                    <div class="row">
                        <!-- Student Profile -->
                        <div class="col-md-4 mb-4">
                            <div class="card summary-card text-center h-100">
                                <div class="card-body">
                                    <img src="<?php echo htmlspecialchars($student_data['photo'] ?: 'uploads/default_student.png'); ?>" alt="Student Photo" class="student-photo mb-3 shadow-sm">
                                    <h5 class="fw-bold"><?php echo htmlspecialchars($student_data['student_name']); ?></h5>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($student_data['student_id']); ?></p>
                                    <span class="badge bg-info px-3 py-2 mt-2">Grade: <?php echo htmlspecialchars($student_data['grade'] . ' (' . ($student_data['section'] ?? 'N/A') . ')'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Summary -->
                        <div class="col-md-8 mb-4">
                            <?php if (!$fee_data): ?>
                                <div class="alert alert-danger text-center h-100 d-flex align-items-center justify-content-center flex-column">
                                    <i class="bi bi-exclamation-triangle-fill fs-1 text-danger mb-2"></i>
                                    <h5>Fee Structure Not Assigned</h5>
                                    <p class="mb-0">Please notify the administrator.</p>
                                </div>
                            <?php else: ?>
                                <div class="card summary-card h-100">
                                    <div class="card-body">
                                        <div class="row mb-4 text-center">
                                            <div class="col-md-4 border-end">
                                                <h6 class="text-muted mb-1">Total Fee</h6>
                                                <h3 class="fw-bold mb-0">₹<?php echo number_format($fee_data['total_fee'], 2); ?></h3>
                                            </div>
                                            <div class="col-md-4 border-end">
                                                <h6 class="text-muted mb-1">Paid Amount</h6>
                                                <h3 class="fw-bold text-success mb-0">₹<?php echo number_format($fee_data['paid_amount'], 2); ?></h3>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="text-muted mb-1">Pending Amount</h6>
                                                <h3 class="fw-bold text-danger mb-0">₹<?php echo number_format($fee_data['pending_amount'], 2); ?></h3>
                                            </div>
                                        </div>

                                        <hr>
                                        <div class="text-center pt-3">
                                            <?php if ($fee_data['pending_amount'] > 0): ?>
                                                <div class="alert alert-warning mb-0">
                                                    <i class="bi bi-info-circle me-2"></i> This student has pending fees. Please direct them to the Administrator for payment.
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-success mb-0">
                                                    <i class="bi bi-check-circle-fill me-2"></i> Fees have been completely paid.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($fee_data && !empty($installments)): ?>
                        <div class="card summary-card mt-4">
                            <div class="card-header bg-white pt-4 pb-2 border-0">
                                <h5 class="fw-bold m-0">Payment History</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Receipt No</th>
                                                <th>Date</th>
                                                <th>Amount Paid</th>
                                                <th>Method</th>
                                                <th>Remarks</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($installments as $inst): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($inst['receipt_no']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($inst['payment_date'])); ?></td>
                                                    <td class="text-success fw-bold">₹<?php echo number_format($inst['amount_paid'], 2); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($inst['payment_method']); ?></span></td>
                                                    <td class="text-muted small"><?php echo htmlspecialchars($inst['remarks']); ?></td>
                                                    <td class="text-end">
                                                        <a href="fee_receipt.php?payment_id=<?php echo $inst['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-download"></i> Receipt
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
