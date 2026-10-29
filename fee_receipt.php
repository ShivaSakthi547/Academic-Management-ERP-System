<?php
// fee_receipt.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$payment_id = intval($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch Payment Details
$stmt = $pdo->prepare("
    SELECT fp.*, sf.total_fee, sf.paid_amount as total_paid, sf.pending_amount, 
           s.student_name, s.student_id, s.grade, s.photo,
           c.section
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    LEFT JOIN class_students cs ON s.id = cs.student_id
    LEFT JOIN classes c ON cs.class_id = c.id
    WHERE fp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Payment record not found.");
}

// Access Control
if ($userRole === 'Student') {
    if ($payment['student_id'] !== $userName) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
        header("Location: student_dashboard.php");
        exit();
    }
}

// Fetch institution info
$inst = [];
try {
    $stmt_inst = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $inst = $stmt_inst->fetch();
} catch (\PDOException $e) {}

$school_name = $inst['school_name'] ?? 'SMART CAMPUS MATRIC HIGHER SECONDARY SCHOOL';
$inst_code = $inst['institution_code'] ?? 'SCMHSS-2000';
$school_address = $inst['address'] ?? '123 Education Street, Knowledge City';
$school_phone = $inst['phone'] ?? '+1 234 567 8900';
$school_email = $inst['email'] ?? 'info@smartcampus.edu';
$school_logo = !empty($inst['logo_path']) ? $inst['logo_path'] : 'uploads/default_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - <?php echo htmlspecialchars($payment['receipt_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f0f2f5; }
        #receipt-container {
            width: 210mm;
            min-height: 148.5mm; /* A5 Landscape or half A4 */
            margin: 20px auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 15mm;
            border-radius: 10px;
        }
        .header-section { border-bottom: 2px solid #0d6efd; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-img { max-height: 70px; }
        .school-title { color: #0d6efd; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 1.5rem; }
        .receipt-title { font-weight: 800; font-size: 1.25rem; letter-spacing: 2px; text-decoration: underline; }
        
        .info-box { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #f8f9fa; }
        .student-photo { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .payment-table th { background: #f8f9fa; }
        .payment-table td, .payment-table th { vertical-align: middle; }
        
        .signature-box { border-top: 1px dashed #000; display: inline-block; padding-top: 5px; width: 200px; text-align: center; margin-top: 50px; }
        .seal-box { width: 100px; height: 100px; border: 2px solid #0d6efd; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0d6efd; opacity: 0.3; transform: rotate(-15deg); margin-left: auto; }
        
        @media print {
            body { background: white; }
            #receipt-container { margin: 0; box-shadow: none; border-radius: 0; padding: 0; width: 100%; min-height: auto; }
            .no-print { display: none !important; }
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container py-4 no-print text-center">
    <button class="btn btn-secondary me-2" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button>
    <button class="btn btn-primary" id="btn-save-pdf"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</button>
    <button class="btn btn-outline-dark ms-2" onclick="history.back()"><i class="bi bi-arrow-left me-2"></i>Back</button>
</div>

<div id="receipt-container">
    <div id="receipt-content">
        <!-- School Header -->
        <div class="header-section text-center">
            <div class="row align-items-center">
                <div class="col-2">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" class="logo-img" alt="Logo" onerror="this.src='uploads/default_logo.png'">
                </div>
                <div class="col-10 text-start">
                    <h2 class="school-title mb-1"><?php echo htmlspecialchars($school_name); ?></h2>
                    <p class="mb-0 text-muted small">
                        <strong>Code:</strong> <?php echo htmlspecialchars($inst_code); ?> | 
                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($school_phone); ?> | 
                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($school_email); ?>
                    </p>
                    <p class="mb-0 text-muted small"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($school_address); ?></p>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <span class="receipt-title">FEE RECEIPT</span>
        </div>

        <!-- Receipt Info -->
        <div class="row mb-4">
            <div class="col-6">
                <h5 class="fw-bold mb-1">Receipt No: <span class="text-danger"><?php echo htmlspecialchars($payment['receipt_no']); ?></span></h5>
            </div>
            <div class="col-6 text-end">
                <h5 class="fw-bold mb-1">Date: <span class="text-primary"><?php echo date('d-M-Y', strtotime($payment['payment_date'])); ?></span></h5>
            </div>
        </div>

        <!-- Student Info -->
        <div class="info-box mb-4">
            <div class="row align-items-center">
                <div class="col-2 text-center">
                    <img src="<?php echo htmlspecialchars($payment['photo'] ?: 'uploads/default_student.png'); ?>" class="student-photo" alt="Student Photo">
                </div>
                <div class="col-5">
                    <p class="mb-1 text-muted small text-uppercase fw-bold">Student Name</p>
                    <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($payment['student_name']); ?></h5>
                </div>
                <div class="col-3">
                    <p class="mb-1 text-muted small text-uppercase fw-bold">Student ID</p>
                    <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($payment['student_id']); ?></h5>
                </div>
                <div class="col-2">
                    <p class="mb-1 text-muted small text-uppercase fw-bold">Grade</p>
                    <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($payment['grade'] . ' ' . ($payment['section'] ?? '')); ?></h5>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <table class="table table-bordered payment-table mb-4">
            <tbody>
                <tr>
                    <th class="w-25">Payment Method</th>
                    <td class="w-25"><span class="badge bg-dark"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                    <th class="w-25">Total Fee</th>
                    <td class="w-25 fw-bold text-end">₹<?php echo number_format($payment['total_fee'], 2); ?></td>
                </tr>
                <tr>
                    <th>Remarks</th>
                    <td><?php echo htmlspecialchars($payment['remarks'] ?: '-'); ?></td>
                    <th>Previous Balance</th>
                    <td class="fw-bold text-end">₹<?php echo number_format($payment['pending_amount'] + $payment['amount_paid'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="2" rowspan="2" class="align-middle">
                        <div class="p-3 bg-light rounded text-center">
                            <h6 class="text-muted text-uppercase mb-1 fw-bold">Amount Paid</h6>
                            <h2 class="text-success fw-bold m-0">₹<?php echo number_format($payment['amount_paid'], 2); ?></h2>
                        </div>
                    </td>
                    <th class="align-middle">Current Payment</th>
                    <td class="align-middle fw-bold text-success text-end border-bottom border-dark border-2">- ₹<?php echo number_format($payment['amount_paid'], 2); ?></td>
                </tr>
                <tr class="table-light">
                    <th class="align-middle text-danger">Pending Balance</th>
                    <td class="align-middle fw-bold text-danger text-end fs-5">₹<?php echo number_format($payment['pending_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="row mt-5 align-items-end">
            <div class="col-6">
                <div class="seal-box fw-bold">
                    OFFICIAL<br>SEAL
                </div>
            </div>
            <div class="col-6 text-end">
                <div class="signature-box">
                    <strong>Authorized Signature</strong>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center text-muted small" style="border-top: 1px dashed #ccc; padding-top: 10px;">
            <p>This is a system generated receipt and does not require a physical signature unless mandated by institution policy.</p>
        </div>

    </div>
</div>

<script>
    document.getElementById('btn-save-pdf').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
        
        const element = document.getElementById('receipt-container');
        const opt = {
            margin:       0,
            filename:     'Receipt_<?php echo $payment['receipt_no']; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Download PDF';
        }).catch(err => {
            console.error('PDF Generation Error:', err);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Download PDF';
            alert('Failed to generate PDF. Please try again.');
        });
    });
</script>

</body>
</html>
