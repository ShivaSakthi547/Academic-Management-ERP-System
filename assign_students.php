<?php
// assign_students.php
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

$class_id = intval($_GET['class_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$class_id]);
    $class_data = $stmt->fetch();
    
    if (!$class_data) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Class not found or inactive.'];
        header("Location: view_classes.php");
        exit();
    }
    
    // Get eligible students who are not assigned to ANY class and whose grade matches the class grade
    $stmt_students = $pdo->prepare("SELECT id, student_id, student_name, grade FROM students WHERE deleted_at IS NULL AND grade = ? AND id NOT IN (SELECT student_id FROM class_students) ORDER BY student_name ASC");
    $stmt_students->execute([$class_data['grade']]);
    $eligible_students = $stmt_students->fetchAll();
    
} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students - Academic Management System</title>
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
                <div class="col-lg-10">
                    <div class="card form-card">
                        <div class="form-header p-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Assign Students</h3>
                                <p class="mb-0 text-white-50">Class: <strong><?php echo htmlspecialchars($class_data['class_name']); ?></strong> (Grade: <?php echo htmlspecialchars($class_data['grade']); ?>)</p>
                            </div>
                            <a href="class_details.php?id=<?php echo $class_id; ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back to Class</a>
                        </div>
                        <div class="card-body p-4">
                            

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary mb-3">Available Unassigned Students</label>
                                
                                <?php if (empty($eligible_students)): ?>
                                    <div class="alert alert-warning">
                                        No unassigned students available.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle border">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Student ID</th>
                                                    <th>Student Name</th>
                                                    <th>Grade</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($eligible_students as $student): ?>
                                                    <tr>
                                                        <td class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                        <td class="fw-bold"><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['grade']); ?></td>
                                                        <td><span class="badge bg-warning text-dark">Unassigned</span></td>
                                                        <td class="text-end">
                                                            <form action="action.php?action=assign_single_student" method="POST" class="d-inline">
                                                                <input type="hidden" name="class_id" value="<?php echo $class_data['id']; ?>">
                                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Assign</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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
