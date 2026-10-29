<?php
// class_form.php
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

$is_edit = false;
$class_data = null;
$id_param = $_GET['id'] ?? null;

if ($id_param !== null) {
    $is_edit = true;
    $id = intval($id_param);
    try {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        $class_data = $stmt->fetch();
        if (!$class_data) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Class not found.'];
            header("Location: view_classes.php");
            exit();
        }
    } catch (\PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

$grades = [
    "First Grade", "Second Grade", "Third Grade", "Fourth Grade",
    "Fifth Grade", "Sixth Grade", "Seventh Grade", "Eighth Grade",
    "Ninth Grade", "Tenth Grade", "Eleventh Grade", "Twelfth Grade"
];

$staff_members = [];
try {
    $stmt = $pdo->query("SELECT id, staff_name FROM staff WHERE status = 1 ORDER BY staff_name ASC");
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    // Ignore error, just an empty list
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Class' : 'Add Class'; ?> - Academic Management System</title>
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
                <div class="col-lg-8">
                    <div class="card form-card">
                        <div class="form-header p-4">
                            <h3 class="fw-bold mb-0"><i class="bi <?php echo $is_edit ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-2"></i><?php echo $is_edit ? 'Edit Class' : 'Add New Class'; ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <form action="action.php?action=<?php echo $is_edit ? 'edit_class' : 'add_class'; ?>" method="POST" class="needs-validation" novalidate>
                                <?php if ($is_edit): ?>
                                    <input type="hidden" name="id" value="<?php echo $class_data['id']; ?>">
                                <?php endif; ?>

                                <div class="mb-4">
                                    <label for="grade" class="form-label fw-semibold text-secondary">Grade</label>
                                    <select class="form-select" id="grade" name="grade" required>
                                        <option value="" disabled selected>Select Grade</option>
                                        <?php 
                                        $selected_grade = $is_edit ? $class_data['grade'] : '';
                                        foreach ($grades as $g) {
                                            $selected = ($selected_grade === $g) ? 'selected' : '';
                                            echo "<option value=\"$g\" $selected>$g</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="section" class="form-label fw-semibold text-secondary">Section</label>
                                    <input type="text" class="form-control" id="section" name="section" placeholder="e.g. A" value="<?php echo htmlspecialchars($is_edit ? $class_data['section'] : ''); ?>" required>
                                    <div class="invalid-feedback">Section is required.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-semibold text-secondary">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($is_edit ? $class_data['description'] : ''); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="class_teacher_id" class="form-label fw-semibold text-secondary">Class Teacher</label>
                                    <select class="form-select" id="class_teacher_id" name="class_teacher_id">
                                        <option value="">-- No Class Teacher Assigned --</option>
                                        <?php 
                                        $selected_teacher = $is_edit ? $class_data['class_teacher_id'] : '';
                                        foreach ($staff_members as $staff) {
                                            $selected = ($selected_teacher == $staff['id']) ? 'selected' : '';
                                            echo "<option value=\"{$staff['id']}\" $selected>" . htmlspecialchars($staff['staff_name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="view_classes.php" class="btn btn-outline-secondary px-4 py-2"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Save Changes' : 'Add Class'; ?></button>
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
