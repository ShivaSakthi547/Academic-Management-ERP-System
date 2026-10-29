<?php
// attendance_history.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'Student') {
    if (isset($_GET['ajax'])) {
        echo json_encode(['success' => false, 'message' => 'Access Denied.']);
        exit();
    }
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access Denied.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'data' => [], 'message' => '', 'stats' => null];

    try {
        if ($userRole === 'Admin') {
            $date = $_GET['date'] ?? '';
            $grade = $_GET['grade'] ?? '';
            $section = $_GET['section'] ?? '';

            if (empty($date) || empty($grade) || empty($section)) {
                $response['message'] = 'Attendance Date, Grade, and Section are required.';
                echo json_encode($response);
                exit();
            }

            // Find class ID for the selected grade and section
            $stmt_class = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND section = ? AND deleted_at IS NULL");
            $stmt_class->execute([$grade, $section]);
            $class_id = $stmt_class->fetchColumn();

            if (!$class_id) {
                $response['success'] = true;
                $response['data'] = [];
                echo json_encode($response);
                exit();
            }

            $query = "
                SELECT a.attendance_date, a.status, s.student_id, s.student_name, c.class_name, c.grade, c.section, stf.staff_name as marked_by_name
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                JOIN classes c ON a.class_id = c.id
                LEFT JOIN staff stf ON a.marked_by = stf.id
                WHERE a.class_id = ? AND a.attendance_date = ?
                ORDER BY s.student_name ASC
            ";
            $stmt_history = $pdo->prepare($query);
            $stmt_history->execute([$class_id, $date]);
            $response['data'] = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;

        } elseif ($userRole === 'Staff') {
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';
            $student_id_filter = $_GET['student_id'] ?? '';

            if (empty($start_date) || empty($end_date) || empty($student_id_filter)) {
                $response['message'] = 'Start Date, End Date, and Student are required.';
                echo json_encode($response);
                exit();
            }

            if (strtotime($end_date) < strtotime($start_date)) {
                $response['message'] = 'End Date cannot be earlier than Start Date.';
                echo json_encode($response);
                exit();
            }

            // Verify staff has access to this student through classes
            $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
            $stmt_staff->execute([$userName]);
            $staff_id = $stmt_staff->fetchColumn();

            $params = [];
            $query = "
                SELECT a.attendance_date, a.status, s.student_id, s.student_name, c.class_name, c.grade, c.section, stf.staff_name as marked_by_name
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                JOIN classes c ON a.class_id = c.id
                LEFT JOIN staff stf ON a.marked_by = stf.id
                WHERE a.attendance_date BETWEEN ? AND ?
                AND c.class_teacher_id = ?
            ";
            $params[] = $start_date;
            $params[] = $end_date;
            $params[] = $staff_id;

            if ($student_id_filter !== 'all') {
                $query .= " AND a.student_id = ?";
                $params[] = $student_id_filter;
            }

            $query .= " ORDER BY a.attendance_date DESC, c.grade ASC, c.section ASC, s.student_name ASC";

            $stmt_history = $pdo->prepare($query);
            $stmt_history->execute($params);
            $history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

            // Calculate Stats
            $total = count($history);
            $present = 0;
            $absent = 0;
            foreach ($history as $row) {
                if ($row['status'] === 'Present') $present++;
                if ($row['status'] === 'Absent') $absent++;
            }
            $percent = $total > 0 ? round(($present / $total) * 100, 2) : 0;

            $response['data'] = $history;
            $response['stats'] = [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'percentage' => $percent
            ];
            $response['success'] = true;
        }
    } catch (PDOException $e) {
        $response['message'] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Initial Page Load Data
$grades = [];
$sections = [];
$students = [];

try {
    if ($userRole === 'Admin') {
        $stmt_grades = $pdo->query("SELECT DISTINCT grade FROM classes WHERE deleted_at IS NULL ORDER BY grade");
        $grades = $stmt_grades->fetchAll(PDO::FETCH_COLUMN);

        $stmt_sections = $pdo->query("SELECT DISTINCT section FROM classes WHERE deleted_at IS NULL ORDER BY section");
        $sections = $stmt_sections->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($userRole === 'Staff') {
        $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
        $stmt_staff->execute([$userName]);
        $staff_id = $stmt_staff->fetchColumn();

        if ($staff_id) {
            $stmt_classes = $pdo->prepare("SELECT id FROM classes WHERE class_teacher_id = ? AND deleted_at IS NULL");
            $stmt_classes->execute([$staff_id]);
            $class_ids = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($class_ids)) {
                $inQuery = implode(',', array_fill(0, count($class_ids), '?'));
                $stmt_all_students = $pdo->prepare("
                    SELECT DISTINCT s.id, s.student_id, s.student_name, c.grade, c.section 
                    FROM students s 
                    JOIN class_students cs ON s.id = cs.student_id 
                    JOIN classes c ON cs.class_id = c.id
                    WHERE c.id IN ($inQuery) AND s.deleted_at IS NULL
                    ORDER BY s.student_name
                ");
                $stmt_all_students->execute($class_ids);
                $students = $stmt_all_students->fetchAll();
            }
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($userRole === 'Staff'): ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .filter-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
        .summary-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .summary-value { font-size: 1.5rem; font-weight: 700; }
        .status-present { color: #198754; font-weight: 600; }
        .status-absent { color: #dc3545; font-weight: 600; }
        .loading-overlay { display: none; text-align: center; padding: 3rem; }
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
                <h1 class="h2">Attendance History</h1>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <form id="filterForm" class="row g-3 align-items-end" onsubmit="event.preventDefault(); fetchAttendance();">
                    <?php if ($userRole === 'Admin'): ?>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Attendance Date</label>
                            <input type="date" class="form-control" id="admin_date" name="date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Grade</label>
                            <select class="form-select" id="admin_grade" name="grade" required>
                                <option value="">Select Grade</option>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo htmlspecialchars($grade); ?>">
                                        <?php echo htmlspecialchars($grade); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Section</label>
                            <select class="form-select" id="admin_section" name="section" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section); ?>">
                                        <?php echo htmlspecialchars($section); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php elseif ($userRole === 'Staff'): ?>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Start Date</label>
                            <input type="date" class="form-control" id="staff_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">End Date</label>
                            <input type="date" class="form-control" id="staff_end_date" name="end_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Student</label>
                            <select id="staff_student" name="student_id" required placeholder="Search Student...">
                                <option value="">Select Student</option>
                                <option value="all">All Students</option>
                                <?php foreach ($students as $stu): ?>
                                    <option value="<?php echo $stu['id']; ?>">
                                        <?php echo htmlspecialchars($stu['student_name'] . ' (' . $stu['student_id'] . ') - Grade ' . $stu['grade'] . ' ' . $stu['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="viewBtn">
                            <i class="bi bi-search me-1"></i>View Attendance
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($userRole === 'Staff'): ?>
            <!-- Summary Stats for Staff (Initially Hidden) -->
            <div id="summaryStats" class="summary-card" style="display: none;">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="text-muted small fw-bold text-uppercase">Total Working Days</div>
                        <div class="summary-value text-dark" id="stat_total">0</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small fw-bold text-uppercase">Present Days</div>
                        <div class="summary-value text-success" id="stat_present">0</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small fw-bold text-uppercase">Absent Days</div>
                        <div class="summary-value text-danger" id="stat_absent">0</div>
                    </div>
                    <div class="col-3">
                        <div class="text-muted small fw-bold text-uppercase">Attendance %</div>
                        <div class="summary-value text-primary" id="stat_percent">0%</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- History Table -->
            <div class="table-card p-0">
                <div id="loadingOverlay" class="loading-overlay">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2 text-muted fw-medium">Fetching attendance records...</div>
                </div>
                
                <div class="table-responsive" id="tableContainer">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Marked By</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTbody">
                            <tr><td colspan="6" class="text-center py-5 text-muted">Please select filters and click View Attendance.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Toast Container for JS Notifications -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="jsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="jsToastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="jsToastBody"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($userRole === 'Staff'): ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        new TomSelect("#staff_student", {
            create: false,
            sortField: { field: "text", direction: "asc" }
        });
    });
</script>
<?php endif; ?>

<script>
    const userRole = '<?php echo $userRole; ?>';
    
    function showToast(type, message) {
        const toastEl = document.getElementById('jsToast');
        const toastTitle = document.getElementById('jsToastTitle');
        const toastBody = document.getElementById('jsToastBody');
        
        toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning');
        
        if (type === 'success') {
            toastEl.classList.add('text-bg-success');
            toastTitle.textContent = 'Success';
        } else if (type === 'error') {
            toastEl.classList.add('text-bg-danger');
            toastTitle.textContent = 'Error';
        } else {
            toastEl.classList.add('text-bg-warning');
            toastTitle.textContent = 'Warning';
        }
        
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    async function fetchAttendance() {
        const form = document.getElementById('filterForm');
        let queryParams = new URLSearchParams(new FormData(form)).toString();
        queryParams += '&ajax=1';

        // Additional JS Validation
        if (userRole === 'Staff') {
            const start = document.getElementById('staff_start_date').value;
            const end = document.getElementById('staff_end_date').value;
            if (new Date(end) < new Date(start)) {
                showToast('error', 'End Date cannot be earlier than Start Date.');
                return;
            }
            if(!document.getElementById('staff_student').value) {
                 showToast('error', 'Please select a student.');
                 return;
            }
        } else if (userRole === 'Admin') {
            if(!document.getElementById('admin_date').value || !document.getElementById('admin_grade').value || !document.getElementById('admin_section').value) {
                showToast('error', 'Please select Date, Grade, and Section.');
                return;
            }
        }

        const tbody = document.getElementById('attendanceTbody');
        const loading = document.getElementById('loadingOverlay');
        const btn = document.getElementById('viewBtn');

        // UI updates for loading
        tbody.innerHTML = '';
        loading.style.display = 'block';
        btn.disabled = true;
        
        if (userRole === 'Staff') {
            document.getElementById('summaryStats').style.display = 'none';
        }

        try {
            const response = await fetch(`attendance_history.php?${queryParams}`);
            const result = await response.json();

            loading.style.display = 'none';
            btn.disabled = false;

            if (result.success) {
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No attendance records found for the selected criteria.</td></tr>';
                    showToast('success', 'Search completed. No records found.');
                } else {
                    let html = '';
                    result.data.forEach(record => {
                        const statusHtml = record.status === 'Present' 
                            ? '<span class="status-present"><i class="bi bi-check-circle-fill me-1"></i>Present</span>'
                            : '<span class="status-absent"><i class="bi bi-x-circle-fill me-1"></i>Absent</span>';
                            
                        html += `
                            <tr>
                                <td class="fw-medium">${formatDate(record.attendance_date)}</td>
                                <td>Grade -${record.grade} Sec- ${record.section}</td>
                                <td class="text-muted">${record.student_id}</td>
                                <td class="fw-bold">${record.student_name}</td>
                                <td>${statusHtml}</td>
                                <td class="small text-muted">${record.marked_by_name || 'System'}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                    showToast('success', 'Attendance records loaded successfully.');
                    
                    if (userRole === 'Staff' && result.stats) {
                        document.getElementById('stat_total').textContent = result.stats.total;
                        document.getElementById('stat_present').textContent = result.stats.present;
                        document.getElementById('stat_absent').textContent = result.stats.absent;
                        document.getElementById('stat_percent').textContent = result.stats.percentage + '%';
                        document.getElementById('summaryStats').style.display = 'block';
                    }
                }
            } else {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">${result.message}</td></tr>`;
                showToast('error', result.message);
            }
        } catch (error) {
            loading.style.display = 'none';
            btn.disabled = false;
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">An error occurred while fetching data.</td></tr>`;
            showToast('error', 'Network error. Please try again.');
            console.error('Error:', error);
        }
    }
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
