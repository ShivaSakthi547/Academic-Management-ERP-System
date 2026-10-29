<?php
// attendance_sheet.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'Student') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access Denied.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$class_id = intval($_GET['class_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if ($class_id <= 0) {
    header("Location: attendance.php");
    exit();
}

try {
    // Verify class and get details
    $stmt_class = $pdo->prepare("SELECT c.*, s.staff_name as class_teacher_name FROM classes c LEFT JOIN staff s ON c.class_teacher_id = s.id WHERE c.id = ?");
    $stmt_class->execute([$class_id]);
    $class = $stmt_class->fetch();

    if (!$class) {
        die("Class not found.");
    }

    $can_edit = false;

    if ($userRole === 'Staff') {
        $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
        $stmt_staff->execute([$userName]);
        $staff_id = $stmt_staff->fetchColumn();

        if ($class['class_teacher_id'] == $staff_id) {
            $can_edit = true;
        } else {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access Denied. Only the Class Teacher can view/edit this sheet.'];
            header("Location: attendance.php");
            exit();
        }
    }

    // Get students in this class
    $stmt_students = $pdo->prepare("
        SELECT s.id, s.student_id, s.student_name, a.status 
        FROM students s 
        JOIN class_students cs ON s.id = cs.student_id 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.class_id = ? AND a.attendance_date = ?
        WHERE cs.class_id = ? AND s.deleted_at IS NULL
        ORDER BY s.student_name ASC
    ");
    $stmt_students->execute([$class_id, $date, $class_id]);
    $students = $stmt_students->fetchAll();

    $total_students = count($students);
    $present_count = 0;
    $absent_count = 0;

    foreach ($students as $stu) {
        if ($stu['status'] === 'Present') $present_count++;
        if ($stu['status'] === 'Absent') $absent_count++;
    }

    $attendance_percentage = $total_students > 0 && ($present_count + $absent_count > 0) ? round(($present_count / ($present_count + $absent_count)) * 100, 2) : 0;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Sheet - <?php echo htmlspecialchars($class['class_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .summary-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .summary-value { font-size: 1.5rem; font-weight: 700; }
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
        .custom-radio {
            display: none;
        }
        .custom-radio-label {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #ced4da;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .radio-present:checked + .custom-radio-label {
            background-color: #198754;
            color: white;
            border-color: #198754;
            box-shadow: 0 4px 10px rgba(25, 135, 84, 0.3);
        }
        .radio-absent:checked + .custom-radio-label {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }
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
                <h1 class="h2">Attendance Sheet <span class="fs-5 text-muted ms-2">| Grade -<?php echo htmlspecialchars($class['grade']); ?> Sec- <?php echo htmlspecialchars($class['section']); ?></span></h1>
                <a href="attendance.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
            </div>

            

            <div class="row align-items-center mb-4">
                <div class="col-md-4">
                    <div class="summary-card text-center h-100">
                        <form id="dateForm" action="attendance_sheet.php" method="GET">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <label class="form-label text-muted fw-bold small text-uppercase">Select Date</label>
                            <input type="date" class="form-control form-control-lg fw-bold text-center text-primary" name="date" value="<?php echo htmlspecialchars($date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="document.getElementById('dateForm').submit();" <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </form>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="summary-card h-100">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="text-muted small fw-bold text-uppercase">Total</div>
                                <div class="summary-value text-dark"><?php echo $total_students; ?></div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small fw-bold text-uppercase">Present</div>
                                <div class="summary-value text-success" id="summary-present"><?php echo $present_count; ?></div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small fw-bold text-uppercase">Absent</div>
                                <div class="summary-value text-danger" id="summary-absent"><?php echo $absent_count; ?></div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small fw-bold text-uppercase">Percentage</div>
                                <div class="summary-value text-primary" id="summary-percent"><?php echo $attendance_percentage; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="w-50">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by Student Name or ID...">
                        </div>
                    </div>
                    <?php if ($can_edit): ?>
                    <div>
                        <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="markAll('Present')"><i class="bi bi-check-all me-1"></i>Mark All Present</button>
                    </div>
                    <?php endif; ?>
                </div>

                <form action="save_attendance.php" method="POST" id="attendanceForm">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($date); ?>">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="attendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr><td colspan="3" class="text-center py-4">No students found in this class.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($students as $stu): ?>
                                        <tr class="student-row">
                                            <td class="stu-id text-muted fw-medium"><?php echo htmlspecialchars($stu['student_id']); ?></td>
                                            <td class="stu-name fw-bold"><?php echo htmlspecialchars($stu['student_name']); ?></td>
                                            <td class="text-center">
                                                <?php if ($can_edit): ?>
                                                    <div class="d-inline-flex gap-2">
                                                        <input type="radio" name="attendance[<?php echo $stu['id']; ?>]" id="present_<?php echo $stu['id']; ?>" value="Present" class="custom-radio radio-present" <?php echo ($stu['status'] === 'Present') ? 'checked' : ''; ?> onchange="updateCounts()">
                                                        <label for="present_<?php echo $stu['id']; ?>" class="custom-radio-label">Present</label>
                                                        
                                                        <input type="radio" name="attendance[<?php echo $stu['id']; ?>]" id="absent_<?php echo $stu['id']; ?>" value="Absent" class="custom-radio radio-absent" <?php echo ($stu['status'] === 'Absent') ? 'checked' : ''; ?> onchange="updateCounts()">
                                                        <label for="absent_<?php echo $stu['id']; ?>" class="custom-radio-label">Absent</label>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($stu['status'] === 'Present'): ?>
                                                        <span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>Present</span>
                                                    <?php elseif ($stu['status'] === 'Absent'): ?>
                                                        <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="bi bi-x-circle me-1"></i>Absent</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary px-3 py-2 rounded-pill">Not Marked</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($can_edit && !empty($students)): ?>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm"><i class="bi bi-save me-2"></i>Save Attendance</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.student-row');
        
        rows.forEach(row => {
            let name = row.querySelector('.stu-name').textContent.toLowerCase();
            let id = row.querySelector('.stu-id').textContent.toLowerCase();
            if (name.includes(filter) || id.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    <?php if ($can_edit): ?>
    // Mark All
    function markAll(status) {
        let radios = document.querySelectorAll('.custom-radio[value="' + status + '"]');
        radios.forEach(r => r.checked = true);
        updateCounts();
    }

    // Dynamic Counts
    function updateCounts() {
        let presents = document.querySelectorAll('.radio-present:checked').length;
        let absents = document.querySelectorAll('.radio-absent:checked').length;
        let total = <?php echo $total_students; ?>;
        
        document.getElementById('summary-present').textContent = presents;
        document.getElementById('summary-absent').textContent = absents;
        
        let marked = presents + absents;
        let percent = marked > 0 ? ((presents / marked) * 100).toFixed(2) : 0;
        document.getElementById('summary-percent').textContent = percent + '%';
    }
    <?php endif; ?>
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
