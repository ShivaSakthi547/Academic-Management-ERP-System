<?php
// student_fees.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$student_id = $_SESSION['user_name']; // username is the student_id string e.g. STU-123

// Fetch student db id
$stmt = $pdo->prepare("SELECT id, grade FROM students WHERE student_id = ? AND deleted_at IS NULL");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student record not found.");
}

$db_id = $student['id'];

// Fetch fee data
$stmt_fee = $pdo->prepare("SELECT * FROM student_fees WHERE student_id = ?");
$stmt_fee->execute([$db_id]);
$fee_data = $stmt_fee->fetch(PDO::FETCH_ASSOC);

$installments = [];
if ($fee_data) {
    // Fetch past payments
    $stmt_payments = $pdo->prepare("SELECT * FROM fee_payments WHERE student_fee_id = ? ORDER BY payment_date DESC, created_at DESC");
    $stmt_payments->execute([$fee_data['id']]);
    $installments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fees - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
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
                <h1 class="h2">My Fees</h1>
            </div>

            <?php if (!$fee_data): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="fs-1 text-secondary mb-3"><i class="bi bi-wallet2"></i></div>
                    <h5>No Fee Data Found</h5>
                    <p class="text-muted">Your fee structure has not been assigned yet. Please contact the administrator.</p>
                </div>
            <?php else: ?>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-primary text-white h-100 p-3">
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Total Fee</h6>
                            <h2 class="fw-bold mb-0">₹<?php echo number_format($fee_data['total_fee'], 2); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-success text-white h-100 p-3">
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Paid Amount</h6>
                            <h2 class="fw-bold mb-0">₹<?php echo number_format($fee_data['paid_amount'], 2); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-danger text-white h-100 p-3">
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Pending Amount</h6>
                            <h2 class="fw-bold mb-0">₹<?php echo number_format($fee_data['pending_amount'], 2); ?></h2>
                        </div>
                    </div>
                </div>

                <div class="card summary-card">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold m-0">Payment History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Receipt No</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Remarks</th>
                                        <th>Amount</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($installments)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No payments made yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($installments as $inst): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($inst['receipt_no']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($inst['payment_date'])); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($inst['payment_method']); ?></span></td>
                                                <td class="text-muted small"><?php echo htmlspecialchars($inst['remarks']); ?></td>
                                                <td class="fw-bold text-success">₹<?php echo number_format($inst['amount_paid'], 2); ?></td>
                                                <td class="text-end pe-4">
                                                    <a href="fee_receipt.php?payment_id=<?php echo $inst['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-download"></i> Receipt
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

            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
