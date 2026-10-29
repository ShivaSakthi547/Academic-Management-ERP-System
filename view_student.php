<?php
// view_student.php
session_start();

// Protect page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$studentId = intval($_GET['id'] ?? 0);

if (!$studentId) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Invalid Student ID.'];
    header("Location: dashboard.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Student not found.'];
        header("Location: dashboard.php");
        exit();
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
    <title>View Student - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .profile-img-container {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            border-radius: 50%;
            padding: 5px;
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.2);
        }
        .student-profile-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            border-radius: 50%; 
            border: 4px solid #fff; 
        }
        .profile-card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); 
            overflow: hidden;
            background: #fff;
        }
        .profile-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 40px 20px 20px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); }
        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 1.1rem;
            color: #212529;
            font-weight: 500;
            margin-bottom: 20px;
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Student Details</h1>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back</a>
                    
                    <!-- Staff and Admin can Edit -->
                    <a href="student_form.php?id=<?php echo $student['id']; ?>" class="btn btn-primary me-2"><i class="bi bi-pencil-fill"></i> Edit Profile</a>

                    <!-- Only Admin can Delete/Restore -->
                    <?php if ($userRole === 'Admin'): ?>
                        <?php if ($student['deleted_at'] === null): ?>
                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $student['id']; ?>)"><i class="bi bi-trash3-fill"></i> Delete</button>
                        <?php else: ?>
                            <button class="btn btn-success" onclick="confirmRestore(<?php echo $student['id']; ?>)"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card profile-card">
                        <div class="profile-header">
                            <div class="profile-img-container mb-3">
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="Profile" class="student-profile-img" onerror="this.src='uploads/default_student.png'">
                            </div>
                            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($student['student_name']); ?></h3>
                            <p class="text-secondary mb-2">ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
                            <span class="badge <?php echo $student['deleted_at'] ? 'bg-danger' : 'bg-success'; ?> fs-6 rounded-pill px-3 py-2">
                                <?php echo $student['deleted_at'] ? 'Inactive' : 'Active'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card detail-card p-4">
                        <h5 class="border-bottom pb-3 mb-4 fw-bold text-primary"><i class="bi bi-info-circle-fill me-2"></i>Student Information</h5>
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-calendar-date me-1"></i> Date of Birth</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($student['date_of_birth'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-gender-ambiguous me-1"></i> Gender</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-award me-1"></i> Grade</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['grade']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-envelope me-1"></i> Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-telephone me-1"></i> Mobile</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['mobile']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-shield-lock me-1"></i> Role</div>
                                <div class="info-value"><span class="badge bg-primary fs-6"><?php echo htmlspecialchars($student['role']); ?></span></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-house-door me-1"></i> Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($student['address'])); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-clock-history me-1"></i> Registered On</div>
                                <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($student['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    function confirmDelete(id) {
        if (confirm("Are you sure you want to move this student to Inactive?")) {
            window.location.href = "action.php?action=delete&id=" + id;
        }
    }
    function confirmRestore(id) {
        if (confirm("Are you sure you want to restore this student?")) {
            window.location.href = "action.php?action=restore&id=" + id;
        }
    }
</script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>