<?php
// fee_structure.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$action = $_POST['action'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'update') {
            $id = $_POST['id'] ?? null;
            $grade = $_POST['grade'];
            $tuition_fee = $_POST['tuition_fee'] ?: 0;
            $exam_fee = $_POST['exam_fee'] ?: 0;
            $lab_fee = $_POST['lab_fee'] ?: 0;
            $library_fee = $_POST['library_fee'] ?: 0;
            $transport_fee = $_POST['transport_fee'] ?: 0;
            $miscellaneous_fee = $_POST['miscellaneous_fee'] ?: 0;
            $status = $_POST['status'] ?? 'Active';

            $total_fee = $tuition_fee + $exam_fee + $lab_fee + $library_fee + $transport_fee + $miscellaneous_fee;

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO fee_structures (grade, tuition_fee, exam_fee, lab_fee, library_fee, transport_fee, miscellaneous_fee, total_fee, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$grade, $tuition_fee, $exam_fee, $lab_fee, $library_fee, $transport_fee, $miscellaneous_fee, $total_fee, $status]);
                $fee_struct_id = $pdo->lastInsertId();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Fee Structure created successfully.'];
            } else {
                $stmt = $pdo->prepare("UPDATE fee_structures SET grade=?, tuition_fee=?, exam_fee=?, lab_fee=?, library_fee=?, transport_fee=?, miscellaneous_fee=?, total_fee=?, status=? WHERE id=?");
                $stmt->execute([$grade, $tuition_fee, $exam_fee, $lab_fee, $library_fee, $transport_fee, $miscellaneous_fee, $total_fee, $status, $id]);
                $fee_struct_id = $id;
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Fee Structure updated successfully.'];
            }
            
            // Sync student_fees for this structure
            if ($status === 'Active') {
                // Update total_fee and pending_amount in existing student_fees for this structure
                $stmtUpdateFees = $pdo->prepare("
                    UPDATE student_fees 
                    SET total_fee = ?, pending_amount = ? - paid_amount 
                    WHERE fee_structure_id = ?
                ");
                $stmtUpdateFees->execute([$total_fee, $total_fee, $fee_struct_id]);

                // Assign this fee structure to students in this grade who don't have ANY fee record yet
                $stmtMissing = $pdo->prepare("
                    SELECT id FROM students 
                    WHERE grade = ? AND deleted_at IS NULL AND id NOT IN (SELECT student_id FROM student_fees)
                ");
                $stmtMissing->execute([$grade]);
                $missing_students = $stmtMissing->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($missing_students)) {
                    $stmtInsertMissing = $pdo->prepare("
                        INSERT INTO student_fees (student_id, fee_structure_id, total_fee, pending_amount) 
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($missing_students as $st_id) {
                        $stmtInsertMissing->execute([$st_id, $fee_struct_id, $total_fee, $total_fee]);
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Fee Structure deleted successfully.'];
        }
        header("Location: fee_structure.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Database error: ' . $e->getMessage()];
    }
}

// Fetch all fee structures
try {
    $stmt = $pdo->query("SELECT * FROM fee_structures ORDER BY FIELD(grade, 'Pre-KG', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12')");
    $fee_structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure Management - Academic Management System</title>
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
                <h1 class="h2">Fee Structure Management</h1>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="resetForm()">
                    <i class="bi bi-plus-lg me-1"></i> Add Fee Structure
                </button>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Grade</th>
                                    <th>Tuition Fee</th>
                                    <th>Exam Fee</th>
                                    <th>Other Fees</th>
                                    <th>Total Fee</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($fee_structures)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No fee structures found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fee_structures as $fs): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($fs['grade']); ?></td>
                                            <td>₹<?php echo number_format($fs['tuition_fee'], 2); ?></td>
                                            <td>₹<?php echo number_format($fs['exam_fee'], 2); ?></td>
                                            <td><small class="text-muted">
                                                Lab: ₹<?php echo number_format($fs['lab_fee'], 2); ?><br>
                                                Lib: ₹<?php echo number_format($fs['library_fee'], 2); ?><br>
                                                Trans: ₹<?php echo number_format($fs['transport_fee'], 2); ?><br>
                                                Misc: ₹<?php echo number_format($fs['miscellaneous_fee'], 2); ?>
                                            </small></td>
                                            <td class="fw-bold text-success">₹<?php echo number_format($fs['total_fee'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $fs['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $fs['status']; ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary" onclick='editFee(<?php echo json_encode($fs); ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this fee structure? This may affect student fee records.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $fs['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
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

<!-- Fee Structure Modal -->
<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="feeId" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Grade</label>
                            <select class="form-select" name="grade" id="grade" required>
                                <option value="" disabled selected>Select Grade</option>
                                <?php 
                                $grades = [
                                    "Pre-KG", "LKG", "UKG",
                                    "First Grade", "Second Grade", "Third Grade", "Fourth Grade",
                                    "Fifth Grade", "Sixth Grade", "Seventh Grade", "Eighth Grade",
                                    "Ninth Grade", "Tenth Grade", "Eleventh Grade", "Twelfth Grade"
                                ];
                                foreach ($grades as $g) {
                                    echo "<option value=\"$g\">$g</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-12"><hr></div>
                        <h6 class="fw-bold mb-3">Fee Breakdown</h6>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tuition Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="tuition_fee" id="tuition_fee" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="exam_fee" id="exam_fee" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lab Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="lab_fee" id="lab_fee" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Library Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="library_fee" id="library_fee" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transport Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="transport_fee" id="transport_fee" value="0">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Miscellaneous Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control fee-input" name="miscellaneous_fee" id="miscellaneous_fee" value="0">
                            </div>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <h5 class="m-0 fw-bold">Total Calculated Fee:</h5>
                                <h4 class="m-0 fw-bold text-success" id="calcTotal">₹0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const feeInputs = document.querySelectorAll('.fee-input');
    const calcTotal = document.getElementById('calcTotal');

    function calculateTotal() {
        let total = 0;
        feeInputs.forEach(input => {
            total += parseFloat(input.value || 0);
        });
        calcTotal.textContent = '₹' + total.toFixed(2);
    }

    feeInputs.forEach(input => {
        input.addEventListener('input', calculateTotal);
    });

    function resetForm() {
        document.getElementById('modalTitle').textContent = 'Add Fee Structure';
        document.getElementById('formAction').value = 'create';
        document.getElementById('feeId').value = '';
        document.getElementById('grade').value = '';
        document.getElementById('status').value = 'Active';
        feeInputs.forEach(input => input.value = 0);
        calculateTotal();
    }

    function editFee(data) {
        document.getElementById('modalTitle').textContent = 'Edit Fee Structure';
        document.getElementById('formAction').value = 'update';
        document.getElementById('feeId').value = data.id;
        document.getElementById('grade').value = data.grade;
        document.getElementById('status').value = data.status;
        
        document.getElementById('tuition_fee').value = data.tuition_fee;
        document.getElementById('exam_fee').value = data.exam_fee;
        document.getElementById('lab_fee').value = data.lab_fee;
        document.getElementById('library_fee').value = data.library_fee;
        document.getElementById('transport_fee').value = data.transport_fee;
        document.getElementById('miscellaneous_fee').value = data.miscellaneous_fee;
        
        calculateTotal();
        new bootstrap.Modal(document.getElementById('feeModal')).show();
    }
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
