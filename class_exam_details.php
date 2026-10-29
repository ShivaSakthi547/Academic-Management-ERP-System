<?php
// class_exam_details.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$class_id = $_GET['class_id'] ?? null;
if (!$class_id) {
    header("Location: exam_schedule.php");
    exit();
}

// Fetch class info
try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class_info) {
        die("Class not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$action = $_POST['action'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create_exam') {
            $exam_name = $_POST['exam_name'];
            $exam_type = $_POST['exam_type'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];

            $stmt = $pdo->prepare("INSERT INTO exams (exam_name, class_id, exam_type, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$exam_name, $class_id, $exam_type, $start_date, $end_date]);
            
            // Auto-generate empty timetable for all subjects
            $exam_id = $pdo->lastInsertId();
            $stmt_sub = $pdo->prepare("SELECT id FROM subjects WHERE class_id = ? AND deleted_at IS NULL");
            $stmt_sub->execute([$class_id]);
            $subjects = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($subjects)) {
                $insert_tt = $pdo->prepare("INSERT INTO exam_timetable (exam_id, class_id, subject_id, exam_date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
                $current_date = new DateTime($start_date);
                $end_date_obj = new DateTime($end_date);
                foreach ($subjects as $sub) {
                    $assigned_date = $current_date->format('Y-m-d');
                    $insert_tt->execute([$exam_id, $class_id, $sub['id'], $assigned_date, '09:00', '12:00']);
                    
                    if ($current_date < $end_date_obj) {
                        $current_date->modify('+1 day');
                    }
                }
            }

            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Exam created successfully!'];
            header("Location: class_exam_details.php?class_id=$class_id");
            exit();

        } elseif ($action === 'toggle_publish') {
            $exam_id = $_POST['exam_id'];
            $is_published = $_POST['is_published'] ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE exams SET is_published = ? WHERE id = ?");
            $stmt->execute([$is_published, $exam_id]);
            
            if ($is_published) {
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                
                $stmt_exam = $pdo->prepare("SELECT exam_name, start_date FROM exams WHERE id = ?");
                $stmt_exam->execute([$exam_id]);
                $exam_data = $stmt_exam->fetch();
                
                $stmt_stu = $pdo->prepare("
                    SELECT s.email, s.student_name 
                    FROM students s 
                    JOIN class_students cs ON s.id = cs.student_id 
                    WHERE cs.class_id = ? AND s.email IS NOT NULL AND s.email != ''
                ");
                $stmt_stu->execute([$class_id]);
                $students = $stmt_stu->fetchAll();
                
                if ($exam_data) {
                    $subject = "Exam Schedule Published: " . $exam_data['exam_name'];
                    foreach ($students as $stu) {
                        $body = "<h3>Exam Schedule Published</h3>
                                 <p>Dear {$stu['student_name']} / Parent,</p>
                                 <p>The schedule for <strong>{$exam_data['exam_name']}</strong> has been published.</p>
                                 <p>The exam will start on <strong>" . date('d M Y', strtotime($exam_data['start_date'])) . "</strong>.</p>
                                 <p>Please log in to the portal to view your detailed timetable.</p>";
                        $mailer->sendEmail($stu['email'], $subject, $body);
                    }
                    
                    // Send to Class Teacher and Subject Teachers
                    $stmt_staff = $pdo->prepare("
                        SELECT DISTINCT st.email, st.staff_name 
                        FROM staff st
                        LEFT JOIN classes c ON c.class_teacher_id = st.id AND c.id = ?
                        LEFT JOIN subject_staff ss ON ss.staff_id = st.id
                        LEFT JOIN subjects sub ON sub.id = ss.subject_id AND sub.class_id = ?
                        WHERE (c.id IS NOT NULL OR sub.id IS NOT NULL) 
                        AND st.email IS NOT NULL AND st.email != ''
                    ");
                    $stmt_staff->execute([$class_id, $class_id]);
                    $staff_members = $stmt_staff->fetchAll();
                    
                    foreach ($staff_members as $staff) {
                        $subject_staff = "Exam Schedule Published: " . $exam_data['exam_name'];
                        $body_staff = "<h3>Exam Schedule Published</h3>
                                 <p>Dear {$staff['staff_name']},</p>
                                 <p>The exam schedule for <strong>{$exam_data['exam_name']}</strong> has been published for your class.</p>
                                 <p>The exam starts on <strong>" . date('d M Y', strtotime($exam_data['start_date'])) . "</strong>.</p>
                                 <p>Please check the staff portal for your timetable and invigilation duties.</p>";
                        $mailer->sendEmail($staff['email'], $subject_staff, $body_staff);
                    }
                }
            }
            
            $status_msg = $is_published ? 'Published' : 'Unpublished';
            $_SESSION['toast'] = ['type' => 'success', 'msg' => "Exam $status_msg successfully!"];
            header("Location: class_exam_details.php?class_id=$class_id");
            exit();
        } elseif ($action === 'delete_exam') {
            $exam_id = $_POST['exam_id'];
            $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->execute([$exam_id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Exam deleted successfully!'];
            header("Location: class_exam_details.php?class_id=$class_id");
            exit();
        } elseif ($action === 'update_timetable') {
            $tt_ids = $_POST['tt_id'];
            $exam_dates = $_POST['exam_date'];
            $start_times = $_POST['start_time'];
            $end_times = $_POST['end_time'];

            $stmt = $pdo->prepare("UPDATE exam_timetable SET exam_date = ?, start_time = ?, end_time = ? WHERE id = ?");
            foreach ($tt_ids as $index => $id) {
                $stmt->execute([$exam_dates[$index], $start_times[$index], $end_times[$index], $id]);
            }
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Timetable updated successfully!'];
            header("Location: class_exam_details.php?class_id=$class_id");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch all exams for this class
try {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE class_id = ? ORDER BY start_date DESC");
    $stmt->execute([$class_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch timetable for each exam
    foreach ($exams as &$exam) {
        $stmt_tt = $pdo->prepare("
            SELECT et.*, s.subject_name 
            FROM exam_timetable et 
            JOIN subjects s ON et.subject_id = s.id 
            WHERE et.exam_id = ?
            ORDER BY et.exam_date ASC, et.start_time ASC
        ");
        $stmt_tt->execute([$exam['id']]);
        $exam['timetable'] = $stmt_tt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($exam); // Fix reference bug
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$exam_types = ['Unit Test 1', 'Unit Test 2', 'Quarterly Exam', 'Half Yearly Exam', 'Model Exam', 'Annual Exam'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Exams - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .exam-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .exam-header { background-color: #fff; padding: 20px; border-bottom: 1px solid #eee; border-radius: 12px 12px 0 0; }
        .exam-body { padding: 20px; background-color: #fff; border-radius: 0 0 12px 12px; }
        .form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer;}
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
                <h1 class="h2">Exams for <span class="text-primary"><?php echo htmlspecialchars($class_info['class_name'] . ' (' . $class_info['grade'] . '-' . $class_info['section'] . ')'); ?></span></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="exam_schedule.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back</a>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
                        <i class="bi bi-plus-lg me-1"></i> Create Exam
                    </button>
                </div>
            </div>

            <?php if (empty($exams)): ?>
                <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="fs-1 text-secondary mb-3"><i class="bi bi-journal-x"></i></div>
                    <h5>No exams created for this class yet.</h5>
                    <p class="text-muted">Click the Create Exam button to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($exams as $exam): ?>
                    <div class="card exam-card">
                        <div class="exam-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($exam['exam_type']); ?></span>
                                <span class="text-muted small"><i class="bi bi-calendar me-1"></i> <?php echo date('d M Y', strtotime($exam['start_date'])) . ' to ' . date('d M Y', strtotime($exam['end_date'])); ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <!-- Publish Toggle -->
                                <form method="POST" action="" class="m-0 d-flex align-items-center">
                                    <input type="hidden" name="action" value="toggle_publish">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                    <input type="hidden" name="is_published" value="<?php echo $exam['is_published'] ? '0' : '1'; ?>">
                                    <div class="form-check form-switch me-2 mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="publishSwitch<?php echo $exam['id']; ?>" <?php echo $exam['is_published'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <label class="form-check-label fw-bold <?php echo $exam['is_published'] ? 'text-success' : 'text-muted'; ?>" for="publishSwitch<?php echo $exam['id']; ?>">
                                            <?php echo $exam['is_published'] ? 'Published' : 'Unpublished'; ?>
                                        </label>
                                    </div>
                                </form>

                                <!-- Hall Allotment Button -->
                                <a href="hall_allotment.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-building me-1"></i> Hall Allotment
                                </a>

                                <!-- Delete Button -->
                                <form method="POST" action="" class="m-0" onsubmit="return confirm('Are you sure you want to delete this exam? This will also delete the timetable and hall allotments.');">
                                    <input type="hidden" name="action" value="delete_exam">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <div class="exam-body">
                            <h5 class="fw-bold mb-3 border-bottom pb-2">Timetable</h5>
                            <?php if (empty($exam['timetable'])): ?>
                                <p class="text-muted small">No subjects found for this class.</p>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_timetable">
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Exam Date</th>
                                                    <th>Start Time</th>
                                                    <th>End Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($exam['timetable'] as $tt): ?>
                                                    <tr>
                                                        <td class="fw-bold">
                                                            <?php echo htmlspecialchars($tt['subject_name']); ?>
                                                            <input type="hidden" name="tt_id[]" value="<?php echo $tt['id']; ?>">
                                                        </td>
                                                        <td><input type="date" name="exam_date[]" class="form-control form-control-sm" value="<?php echo $tt['exam_date']; ?>" required></td>
                                                        <td><input type="time" name="start_time[]" class="form-control form-control-sm" value="<?php echo $tt['start_time']; ?>" required></td>
                                                        <td><input type="time" name="end_time[]" class="form-control form-control-sm" value="<?php echo $tt['end_time']; ?>" required></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-save me-1"></i> Save Timetable</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Create Exam Modal -->
<div class="modal fade" id="createExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create New Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_exam">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Exam Name</label>
                        <input type="text" class="form-control" name="exam_name" required placeholder="e.g. Mid Term Exam 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Exam Type</label>
                        <select class="form-select" name="exam_type" required>
                            <option value="">Select Type...</option>
                            <?php foreach ($exam_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
