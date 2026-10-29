<?php
// collect_fee.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
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

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_fee') {
    $student_fee_id = $_POST['student_fee_id'];
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Generate Receipt No (e.g., REC-2026-0001)
        $year = date('Y');
        $stmt_rec = $pdo->query("SELECT COUNT(*) FROM fee_payments WHERE receipt_no LIKE 'REC-$year-%'");
        $count = $stmt_rec->fetchColumn() + 1;
        $receipt_no = "REC-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Insert payment
        $stmt_pay = $pdo->prepare("INSERT INTO fee_payments (student_fee_id, receipt_no, amount_paid, payment_date, payment_method, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_pay->execute([$student_fee_id, $receipt_no, $amount_paid, $payment_date, $payment_method, $remarks]);
        $payment_id = $pdo->lastInsertId();

        // Update student_fees
        $stmt_sf = $pdo->prepare("SELECT * FROM student_fees WHERE id = ? FOR UPDATE");
        $stmt_sf->execute([$student_fee_id]);
        $sf = $stmt_sf->fetch(PDO::FETCH_ASSOC);

        $new_paid = $sf['paid_amount'] + $amount_paid;
        $new_pending = $sf['total_fee'] - $new_paid;
        
        $status = 'Pending';
        if ($new_paid >= $sf['total_fee']) {
            $status = 'Paid';
        } elseif ($new_paid > 0) {
            $status = 'Partial';
        }

        $stmt_upd = $pdo->prepare("UPDATE student_fees SET paid_amount = ?, pending_amount = ?, status = ? WHERE id = ?");
        $stmt_upd->execute([$new_paid, $new_pending, $status, $student_fee_id]);

        $pdo->commit();
        
        // Send Payment Confirmation Email
        require_once __DIR__ . '/includes/Mailer.php';
        $mailer = new Mailer($pdo);
        
        $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = (SELECT student_id FROM student_fees WHERE id = ?)");
        $stmt_stu->execute([$student_fee_id]);
        $stu_data = $stmt_stu->fetch();
        
        if ($stu_data && !empty($stu_data['email'])) {
            $subject = "Fee Payment Confirmation - $receipt_no";
            $body = "<h3>Payment Confirmation</h3>
                     <p>Dear {$stu_data['student_name']} / Parent,</p>
                     <p>We have successfully received your fee payment.</p>
                     <ul>
                        <li><strong>Receipt No:</strong> $receipt_no</li>
                        <li><strong>Amount Paid:</strong> ₹" . number_format($amount_paid, 2) . "</li>
                        <li><strong>Payment Date:</strong> " . date('d-M-Y', strtotime($payment_date)) . "</li>
                        <li><strong>Payment Method:</strong> $payment_method</li>
                     </ul>
                     <p>Please find the official receipt attached to this email.</p>";
            
            $html_receipt = "
                <h1 style='text-align:center; color:#0d6efd;'>SMART CAMPUS</h1>
                <h2 style='text-align:center;'>FEE RECEIPT</h2>
                <hr>
                <table cellpadding='5'>
                    <tr><td><strong>Receipt No:</strong></td><td>$receipt_no</td></tr>
                    <tr><td><strong>Date:</strong></td><td>" . date('d-M-Y', strtotime($payment_date)) . "</td></tr>
                    <tr><td><strong>Student Name:</strong></td><td>{$stu_data['student_name']}</td></tr>
                    <tr><td><strong>Amount Paid:</strong></td><td>Rs. " . number_format($amount_paid, 2) . "</td></tr>
                    <tr><td><strong>Payment Method:</strong></td><td>$payment_method</td></tr>
                    <tr><td><strong>Remarks:</strong></td><td>$remarks</td></tr>
                </table>
                <hr>
                <p style='text-align:center;'>This is a system generated receipt.</p>
            ";
            
            $pdf_attachment = $mailer->generatePDF($html_receipt);
            $mailer->sendEmail($stu_data['email'], $subject, $body, $pdf_attachment, "Receipt_$receipt_no.pdf");
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Fee collected successfully.'];
        header("Location: collect_fee.php?search=" . urlencode($search_query));
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Payment failed: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collect Fees - Academic Management System</title>
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
                <h1 class="h2">Collect Fees</h1>
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

                        <!-- Fee Summary & Collection -->
                        <div class="col-md-8 mb-4">
                            <?php if (!$fee_data): ?>
                                <div class="alert alert-danger text-center h-100 d-flex align-items-center justify-content-center flex-column">
                                    <i class="bi bi-exclamation-triangle-fill fs-1 text-danger mb-2"></i>
                                    <h5>Fee Structure Not Assigned</h5>
                                    <p class="mb-0">Please check the Fee Structure for Grade <?php echo htmlspecialchars($student_data['grade']); ?>.</p>
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

                                        <?php if ($fee_data['pending_amount'] > 0): ?>
                                            <h5 class="fw-bold mb-3">Record Payment</h5>
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="collect_fee">
                                                <input type="hidden" name="student_fee_id" value="<?php echo $fee_data['id']; ?>">
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Amount Paying</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" step="0.01" max="<?php echo $fee_data['pending_amount']; ?>" class="form-control" name="amount_paid" required placeholder="0.00">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Payment Date</label>
                                                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Payment Method</label>
                                                        <select class="form-select" name="payment_method" required>
                                                            <option value="Cash">Cash</option>
                                                            <option value="UPI">UPI</option>
                                                            <option value="Card">Card</option>
                                                            <option value="Bank Transfer">Bank Transfer</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Remarks (Optional)</label>
                                                        <input type="text" class="form-control" name="remarks" placeholder="Transaction ID, etc.">
                                                    </div>
                                                </div>
                                                <div class="text-end mt-2">
                                                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-check-circle me-1"></i> Save Payment</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-success text-center">
                                                <i class="bi bi-check-circle-fill me-2"></i> Fees have been completely paid.
                                            </div>
                                        <?php endif; ?>
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
                                                            <i class="bi bi-file-earmark-pdf"></i> Receipt
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
