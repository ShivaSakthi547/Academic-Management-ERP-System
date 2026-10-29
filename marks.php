<?php
// marks.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

if ($userRole !== 'Admin' && $userRole !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

// Search logic
$search = trim($_GET['search'] ?? '');

$params = [];
if ($userRole === 'Staff') {
    $sql = "SELECT DISTINCT s.*, c.class_name, c.grade as class_grade, c.section 
            FROM students s
            JOIN class_students cs ON s.id = cs.student_id
            JOIN classes c ON cs.class_id = c.id
            JOIN subjects sub ON c.id = sub.class_id
            JOIN subject_staff ss ON sub.id = ss.subject_id
            JOIN staff stf ON ss.staff_id = stf.id
            WHERE s.deleted_at IS NULL AND stf.staff_name = ? AND s.grade = c.grade";
    $params[] = $userName;
} else {
    $sql = "SELECT DISTINCT s.*, c.class_name, c.grade as class_grade, c.section 
            FROM students s
            JOIN class_students cs ON s.id = cs.student_id
            JOIN classes c ON cs.class_id = c.id
            WHERE s.deleted_at IS NULL AND s.grade = c.grade";
}

if (!empty($search)) {
    $sql .= " AND (s.student_name LIKE ? OR s.student_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.grade, c.section, s.student_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database fetch failed: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .photo-thumbnail { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid #e9ecef; }
        .student-table th {
            font-weight: 600;
            background-color: #f1f3f7;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .student-table tbody tr {
            transition: background-color 0.15s ease;
            cursor: pointer;
        }
        .student-table tbody tr:hover {
            background-color: #e2e8f0;
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
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Marks Management</h1>
            </div>

            

            <!-- Search -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <div class="row align-items-center justify-content-between g-3">
                    <div class="col-md-6">
                        <form action="marks.php" method="GET" class="d-flex align-items-center mb-0">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control py-2" placeholder="Search by Student ID or Name..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary px-4" type="submit">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="marks.php" class="btn btn-outline-secondary px-3 d-flex align-items-center justify-content-center" title="Clear Search">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Student List Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-journal-check text-primary me-2"></i>
                        <span>Select Student for Marks</span>
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-light text-secondary ms-2 small">Search: "<?php echo htmlspecialchars($search); ?>"</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover student-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Photo</th>
                                    <th class="py-3">Student ID</th>
                                    <th class="py-3">Name</th>
                                    <th class="py-3">Grade</th>
                                    <th class="py-3">Section</th>
                                    <th class="py-3 pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x display-6 mb-2 d-block text-secondary"></i>
                                            No students found assigned to classes.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr onclick="window.location.href='student_marks.php?id=<?php echo $student['id']; ?>'">
                                            <td class="ps-4 py-3"><img src="<?php echo htmlspecialchars($student['photo']); ?>" class="photo-thumbnail"></td>
                                            <td class="fw-semibold text-primary"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['grade']); ?></span></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['section']); ?></span></td>
                                            <td class="pe-4 text-end">
                                                <a href="student_marks.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Manage Marks">
                                                    Manage Marks <i class="bi bi-arrow-right-short"></i>
                                                </a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
