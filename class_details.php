<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Only Admin can view classes
if ($userRole !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

$class_id = intval($_GET['id'] ?? 0);

if (!$class_id) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Invalid Class ID.'];
    header("Location: view_classes.php");
    exit();
}

try {
    // Get class info
    $stmt = $pdo->prepare("
        SELECT c.*, s.staff_name as class_teacher_name 
        FROM classes c 
        LEFT JOIN staff s ON c.class_teacher_id = s.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Class not found.'];
        header("Location: view_classes.php");
        exit();
    }

    // Get assigned students
    $stmt_st = $pdo->prepare("
        SELECT st.id, st.student_id, st.student_name, st.gender 
        FROM class_students cs 
        JOIN students st ON cs.student_id = st.id 
        WHERE cs.class_id = ? AND st.deleted_at IS NULL
        ORDER BY st.student_name ASC
    ");
    $stmt_st->execute([$class_id]);
    $students = $stmt_st->fetchAll(PDO::FETCH_ASSOC);

    // Get assigned subjects
    $stmt_sub = $pdo->prepare("
        SELECT sub.id, sub.subject_name, sub.description, sub.deleted_at, 
               st.staff_name, st.major_subject
        FROM subjects sub 
        LEFT JOIN subject_staff ss ON sub.id = ss.subject_id
        LEFT JOIN staff st ON ss.staff_id = st.id
        WHERE sub.class_id = ? AND sub.deleted_at IS NULL
        ORDER BY sub.subject_name ASC
    ");
    $stmt_sub->execute([$class_id]);
    $subjects = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Details - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .detail-card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); }
        .info-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; font-weight: 600; margin-bottom: 5px; }
        .info-value { font-size: 1.1rem; color: #212529; font-weight: 500; margin-bottom: 20px; }
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

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Class Details: <?php echo htmlspecialchars($class['class_name']); ?></h1>
                <div>
                    <a href="view_classes.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to Classes</a>
                    <a href="class_form.php?id=<?php echo $class['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-fill"></i> Edit Class</a>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card detail-card p-4">
                        <h5 class="border-bottom pb-3 mb-4 fw-bold text-primary"><i class="bi bi-info-circle-fill me-2"></i>General Information</h5>
                        <div class="row g-4">
                            <div class="col-sm-3">
                                <div class="info-label"><i class="bi bi-award me-1"></i> Grade</div>
                                <div class="info-value"><?php echo htmlspecialchars($class['grade']); ?></div>
                            </div>
                            <div class="col-sm-3">
                                <div class="info-label"><i class="bi bi-grid-3x3 me-1"></i> Section</div>
                                <div class="info-value"><?php echo htmlspecialchars($class['section']); ?></div>
                            </div>
                            <div class="col-sm-3">
                                <div class="info-label"><i class="bi bi-person-badge me-1"></i> Class Teacher</div>
                                <div class="info-value">
                                    <?php echo $class['class_teacher_name'] ? htmlspecialchars($class['class_teacher_name']) : '<span class="text-muted">Not Assigned</span>'; ?>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="info-label"><i class="bi bi-activity me-1"></i> Status</div>
                                <div class="info-value">
                                    <?php if ($class['deleted_at'] === null): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-card-text me-1"></i> Description</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($class['description'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card detail-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <h5 class="fw-bold text-success mb-0"><i class="bi bi-people-fill me-2"></i>Students (<?php echo count($students); ?>)</h5>
                            <a href="assign_students.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-person-plus-fill me-1"></i>Manage Students</a>
                        </div>
                        <?php if (empty($students)): ?>
                            <div class="alert alert-light border text-center text-muted py-4">No students assigned to this class.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($students as $st): ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($st['student_name']); ?></div>
                                            <div class="small text-muted">ID: <?php echo htmlspecialchars($st['student_id']); ?> | <?php echo htmlspecialchars($st['gender']); ?></div>
                                        </div>
                                        <div>
                                            <a href="transfer_student.php?student_id=<?php echo $st['id']; ?>&from_class=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-primary" title="Transfer Student"><i class="bi bi-arrow-left-right"></i> Transfer</a>
                                            <a href="action.php?action=remove_student_from_class&student_id=<?php echo $st['id']; ?>&class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-danger" title="Remove Student" onclick="return confirm('Remove this student from the class?');"><i class="bi bi-person-x"></i> Remove</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card detail-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <h5 class="fw-bold text-warning mb-0"><i class="bi bi-book-half me-2"></i>Subjects (<?php echo count($subjects); ?>)</h5>
                            <a href="subject_form.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-warning text-dark"><i class="bi bi-plus-circle me-1"></i>Add Subject</a>
                        </div>
                        <?php if (empty($subjects)): ?>
                            <div class="alert alert-light border text-center text-muted py-4">No subjects added for this class.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($subjects as $sub): ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                            <div class="small text-muted mb-1"><?php echo htmlspecialchars($sub['description']); ?></div>
                                            <div class="small">
                                                <i class="bi bi-person-badge text-primary"></i> 
                                                Assigned Staff: <?php echo $sub['staff_name'] ? htmlspecialchars($sub['staff_name']) . ' <span class="text-muted">(Major: ' . htmlspecialchars($sub['major_subject'] ?: 'None') . ')</span>' : '<span class="text-muted">Not Assigned</span>'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($sub['deleted_at'] === null): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success me-2">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger me-2">Inactive</span>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <a href="assign_staff.php?subject_id=<?php echo $sub['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="Assign Staff"><i class="bi bi-person-plus"></i> Assign</a>
                                                <a href="subject_form.php?id=<?php echo $sub['id']; ?>&class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit Subject"><i class="bi bi-pencil"></i></a>
                                                <?php if ($sub['deleted_at'] === null): ?>
                                                    <a href="action.php?action=delete_subject_class&id=<?php echo $sub['id']; ?>&class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete Subject" onclick="return confirm('Soft delete this subject?');"><i class="bi bi-trash"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
