<?php
// subject_form.php
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
$subject_data = null;
$id_param = $_GET['id'] ?? null;
$class_id_param = $_GET['class_id'] ?? null;

if ($id_param !== null) {
    $is_edit = true;
    $id = intval($id_param);
    try {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        $subject_data = $stmt->fetch();
        if (!$subject_data) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Subject not found.'];
            header("Location: view_subjects.php");
            exit();
        }
    } catch (\PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Fetch classes for the dropdown
try {
    $stmt = $pdo->query("SELECT id, class_name, grade FROM classes WHERE deleted_at IS NULL ORDER BY class_name ASC");
    $classes = $stmt->fetchAll();
} catch (\PDOException $e) {
    $classes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Subject' : 'Add Subject'; ?> - Academic Management System</title>
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
                            <h3 class="fw-bold mb-0"><i class="bi <?php echo $is_edit ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-2"></i><?php echo $is_edit ? 'Edit Subject' : 'Add New Subject'; ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <form action="action.php?action=<?php echo $is_edit ? 'edit_subject' : 'add_subject'; ?>" method="POST" class="needs-validation" novalidate>
                                <?php if ($is_edit): ?>
                                    <input type="hidden" name="id" value="<?php echo $subject_data['id']; ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="class_id" class="form-label fw-semibold text-secondary">Class</label>
                                    <select class="form-select" id="class_id" name="class_id" required>
                                        <option value="" disabled selected>Select a Class</option>
                                        <?php 
                                        $selected_class = $is_edit ? $subject_data['class_id'] : $class_id_param;
                                        foreach ($classes as $c) {
                                            $selected = ($selected_class == $c['id']) ? 'selected' : '';
                                            echo "<option value=\"{$c['id']}\" $selected>" . htmlspecialchars($c['class_name'] . ' (' . $c['grade'] . ')') . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a class.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="subject_name" class="form-label fw-semibold text-secondary">Subject Name</label>
                                    <input type="text" class="form-control" id="subject_name" name="subject_name" placeholder="e.g. Mathematics" value="<?php echo htmlspecialchars($is_edit ? $subject_data['subject_name'] : ''); ?>" required>
                                    <div class="invalid-feedback">Subject name is required.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label fw-semibold text-secondary">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($is_edit ? $subject_data['description'] : ''); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <?php 
                                    $cancel_link = "view_subjects.php";
                                    if ($is_edit && !empty($subject_data['class_id'])) {
                                        $cancel_link = "class_details.php?id=" . $subject_data['class_id'];
                                    } elseif (!empty($class_id_param)) {
                                        $cancel_link = "class_details.php?id=" . $class_id_param;
                                    }
                                    ?>
                                    <a href="<?php echo $cancel_link; ?>" class="btn btn-outline-secondary px-4 py-2"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Save Changes' : 'Add Subject'; ?></button>
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
