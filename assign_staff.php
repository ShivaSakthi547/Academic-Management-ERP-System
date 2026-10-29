<?php
// assign_staff.php
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

$subject_id = intval($_GET['subject_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $selected_staff = $_POST['staff'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Remove existing assignments for this subject
        $stmt_del = $pdo->prepare("DELETE FROM subject_staff WHERE subject_id = ?");
        $stmt_del->execute([$subject_id]);
        
        // Insert new assignments
        if (!empty($selected_staff)) {
            $stmt_ins = $pdo->prepare("INSERT INTO subject_staff (subject_id, staff_id) VALUES (?, ?)");
            foreach ($selected_staff as $staff_id) {
                $stmt_ins->execute([$subject_id, intval($staff_id)]);
            }
        }
        
        $pdo->commit();
        
        // Subject Teacher Email Notification
        if (!empty($selected_staff)) {
            require_once __DIR__ . '/includes/Mailer.php';
            $mailer = new Mailer($pdo);
            $stmt_sub = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
            $stmt_sub->execute([$subject_id]);
            $sub_name = $stmt_sub->fetchColumn();
            
            foreach ($selected_staff as $staff_id) {
                $stmt_staff = $pdo->prepare("SELECT staff_name, email FROM staff WHERE id = ?");
                $stmt_staff->execute([$staff_id]);
                $staff = $stmt_staff->fetch();
                if ($staff && !empty($staff['email']) && $sub_name) {
                    $subject = "Subject Teacher Assignment";
                    $body = "<h3>Subject Teacher Assignment</h3>
                             <p>Dear {$staff['staff_name']},</p>
                             <p>You have been assigned as a subject teacher for <strong>{$sub_name}</strong>.</p>
                             <p>Please check your dashboard for class details and timetable schedules.</p>";
                    $mailer->sendEmail($staff['email'], $subject, $body);
                }
            }
        }

        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Staff assigned successfully.'];
        
        // Fetch class_id for redirect
        $stmt_class = $pdo->prepare("SELECT class_id FROM subjects WHERE id = ?");
        $stmt_class->execute([$subject_id]);
        $class_id = $stmt_class->fetchColumn();
        
        if ($class_id) {
            header("Location: class_details.php?id=" . $class_id);
        } else {
            header("Location: view_subjects.php");
        }
        exit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Assignment failed: ' . $e->getMessage()];
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$subject_id]);
    $subject_data = $stmt->fetch();
    
    if (!$subject_data) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Subject not found or inactive.'];
        header("Location: view_subjects.php");
        exit();
    }
    
    // Get all staff, order by matching major subject first, then by name
    $stmt_staff = $pdo->prepare("SELECT id, staff_id, staff_name, major_subject FROM staff WHERE status = 1 ORDER BY (major_subject = ?) DESC, staff_name ASC");
    $stmt_staff->execute([$subject_data['subject_name']]);
    $eligible_staff = $stmt_staff->fetchAll();
    
    // Get currently assigned staff
    $stmt_assigned = $pdo->prepare("SELECT staff_id FROM subject_staff WHERE subject_id = ?");
    $stmt_assigned->execute([$subject_id]);
    $assigned_ids = $stmt_assigned->fetchAll(\PDO::FETCH_COLUMN);
    
} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Staff - Academic Management System</title>
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
                            <h3 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i>Assign Staff</h3>
                            <p class="mb-0 text-white-50">Subject: <strong><?php echo htmlspecialchars($subject_data['subject_name']); ?></strong></p>
                        </div>
                        <div class="card-body p-4">
                            

                            <form action="assign_staff.php" method="POST">
                                <input type="hidden" name="subject_id" value="<?php echo $subject_data['id']; ?>">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-secondary mb-3">Select Staff to Assign</label>
                                    
                                    <?php if (empty($eligible_staff)): ?>
                                        <div class="alert alert-warning">
                                            No active staff found.
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($eligible_staff as $staff): 
                                                $isMatch = (strcasecmp(trim($staff['major_subject']), trim($subject_data['subject_name'])) === 0);
                                            ?>
                                                <label class="list-group-item d-flex align-items-center <?php echo $isMatch ? 'bg-light border-primary' : ''; ?>">
                                                    <input class="form-check-input me-3" type="checkbox" name="staff[]" value="<?php echo $staff['id']; ?>" <?php echo in_array($staff['id'], $assigned_ids) ? 'checked' : ''; ?>>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($staff['staff_name']); ?>
                                                            <?php if ($isMatch): ?>
                                                                <span class="badge bg-primary ms-2"><i class="bi bi-star-fill me-1"></i>Recommended Match</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="small text-muted">ID: <?php echo htmlspecialchars($staff['staff_id']); ?> | Major: <?php echo htmlspecialchars($staff['major_subject'] ?: 'None'); ?></div>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="class_details.php?id=<?php echo $subject_data['class_id']; ?>" class="btn btn-outline-secondary px-4 py-2"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-save me-1"></i>Save Assignments</button>
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
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
