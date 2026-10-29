<?php
// transfer_student.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
if ($userRole !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

$student_id = intval($_GET['student_id'] ?? 0);
$from_class = intval($_GET['from_class'] ?? 0);

if (!$student_id || !$from_class) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Invalid parameters.'];
    header("Location: view_classes.php");
    exit();
}

try {
    // Get student details
    $stmt_st = $pdo->prepare("SELECT id, student_id, student_name, grade FROM students WHERE id = ? AND deleted_at IS NULL");
    $stmt_st->execute([$student_id]);
    $student = $stmt_st->fetch();

    if (!$student) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Student not found.'];
        header("Location: class_details.php?id=" . $from_class);
        exit();
    }

    // Get current class details
    $stmt_c = $pdo->prepare("SELECT id, class_name, grade, section FROM classes WHERE id = ?");
    $stmt_c->execute([$from_class]);
    $current_class = $stmt_c->fetch();

    // Get available classes in the SAME grade (excluding current class)
    $stmt_avail = $pdo->prepare("SELECT id, class_name, section FROM classes WHERE grade = ? AND id != ? AND deleted_at IS NULL ORDER BY class_name ASC");
    $stmt_avail->execute([$student['grade'], $from_class]);
    $available_classes = $stmt_avail->fetchAll();

} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Student - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .form-card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }
        .form-header { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-top-left-radius: 12px; border-top-right-radius: 12px; color: #ffffff; }
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

            <div class="row justify-content-center mt-4">
                <div class="col-lg-6">
                    <div class="card form-card">
                        <div class="form-header p-4">
                            <h3 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2"></i>Transfer Student</h3>
                            <p class="mb-0 text-white-50">Move student to another section</p>
                        </div>
                        <div class="card-body p-4">
                            

                            <div class="mb-4 p-3 bg-light rounded border">
                                <h6 class="fw-bold text-secondary mb-2">Student Information</h6>
                                <div class="fs-5 fw-semibold text-dark"><?php echo htmlspecialchars($student['student_name']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)</div>
                                <div class="text-muted small">Current Class: <span class="fw-bold"><?php echo htmlspecialchars($current_class['class_name']); ?></span></div>
                            </div>

                            <form action="action.php?action=transfer_student" method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <input type="hidden" name="from_class" value="<?php echo $from_class; ?>">

                                <div class="mb-4">
                                    <label for="to_class" class="form-label fw-semibold text-secondary">Destination Class (Grade <?php echo htmlspecialchars($student['grade']); ?>)</label>
                                    
                                    <?php if (empty($available_classes)): ?>
                                        <div class="alert alert-warning mb-0">
                                            No other classes available in Grade <?php echo htmlspecialchars($student['grade']); ?>.
                                        </div>
                                    <?php else: ?>
                                        <select class="form-select" id="to_class" name="to_class" required>
                                            <option value="" disabled selected>Select Destination Section...</option>
                                            <?php foreach ($available_classes as $ac): ?>
                                                <option value="<?php echo $ac['id']; ?>"><?php echo htmlspecialchars($ac['class_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a destination class.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="class_details.php?id=<?php echo $from_class; ?>" class="btn btn-outline-secondary px-4 py-2"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                                    <?php if (!empty($available_classes)): ?>
                                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-check-circle me-1"></i>Confirm Transfer</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
