<?php
// profile.php
session_start();

// Protect page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$profile = null;

try {
    if ($userRole === 'Admin') {
        $stmt = $pdo->prepare("SELECT admin_name AS name, admin_id AS id_number, dob, address, email, phone_number, major_subject, status, photo FROM admins WHERE admin_name = ?");
        $stmt->execute([$userName]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($userRole === 'Staff') {
        $stmt = $pdo->prepare("SELECT staff_name AS name, staff_id AS id_number, dob, address, email, phone_number, major_subject, status, photo FROM staff WHERE staff_name = ?");
        $stmt->execute([$userName]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Just in case it's a student or other role
        $stmt = $pdo->prepare("SELECT student_name AS name, student_id AS id_number, date_of_birth AS dob, address, photo, deleted_at FROM students WHERE student_id = ?");
        $stmt->execute([$userName]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $profile['status'] = is_null($profile['deleted_at']) ? 1 : 0;
        }
    }
    
    if (!$profile) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Profile not found.'];
        header("Location: dashboard.php");
        exit();
    }
    if ($userRole === 'Staff') {
        $stmt_staff = $pdo->prepare("SELECT id FROM staff WHERE staff_name = ?");
        $stmt_staff->execute([$userName]);
        $staff_id_db = $stmt_staff->fetchColumn();

        if ($staff_id_db) {
            $stmt_as = $pdo->prepare("SELECT s.subject_name, c.class_name, c.grade 
                                      FROM subject_staff ss 
                                      JOIN subjects s ON ss.subject_id = s.id 
                                      JOIN classes c ON s.class_id = c.id 
                                      WHERE ss.staff_id = ?");
            $stmt_as->execute([$staff_id_db]);
            $assigned_subjects = $stmt_as->fetchAll();
            
            $stmt_ct = $pdo->prepare("SELECT class_name, grade FROM classes WHERE class_teacher_id = ?");
            $stmt_ct->execute([$staff_id_db]);
            $assigned_classes_rows = $stmt_ct->fetchAll();
            $assigned_classes = [];
            foreach ($assigned_classes_rows as $row) {
                $assigned_classes[] = $row['class_name'] . ' (' . $row['grade'] . ')';
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
    <title>My Profile - Academic Management System</title>
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
        .profile-img { 
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
        .profile-body {
            padding: 30px;
        }
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
                <h1 class="h2">My Profile</h1>
            </div>

            <?php if ($userRole === 'Student'): ?>
                <?php include 'student_profile.php'; ?>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card profile-card">
                        <div class="profile-header">
                            <div class="profile-img-container mb-3">
                                <img src="<?php echo htmlspecialchars($profile['photo'] ?? 'uploads/default_avatar.png'); ?>" alt="Profile Photo" class="profile-img" onerror="this.src='https://github.com/twbs.png'">
                            </div>
                            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($profile['name']); ?></h3>
                            <p class="text-secondary mb-2">ID: <?php echo htmlspecialchars($profile['id_number']); ?></p>
                            <span class="badge <?php echo ($profile['status'] == 1) ? 'bg-success' : 'bg-danger'; ?> fs-6 rounded-pill px-3 py-2">
                                <?php echo ($profile['status'] == 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="profile-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="info-label"><i class="bi bi-calendar-date me-1"></i> Date of Birth</div>
                                    <div class="info-value"><?php echo date('F d, Y', strtotime($profile['dob'])); ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label"><i class="bi bi-shield-lock me-1"></i> Role</div>
                                    <div class="info-value">
                                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($userRole); ?></span>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="info-label"><i class="bi bi-house-door me-1"></i> Address</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($profile['address'])); ?></div>
                                </div>
                                <?php if ($userRole === 'Admin' || $userRole === 'Staff'): ?>
                                <div class="col-sm-6 mt-2">
                                    <div class="info-label"><i class="bi bi-envelope me-1"></i> Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['email']); ?></div>
                                </div>
                                <div class="col-sm-6 mt-2">
                                    <div class="info-label"><i class="bi bi-telephone me-1"></i> Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['phone_number']); ?></div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="info-label"><i class="bi bi-book me-1"></i> Major Subject</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['major_subject']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($userRole === 'Staff'): ?>
                            <hr class="my-4">
                            <h5 class="fw-bold text-success mb-3"><i class="bi bi-journal-check me-2"></i>Assignments</h5>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="info-label"><i class="bi bi-building me-1"></i> Assigned Classes</div>
                                    <div class="info-value mb-0">
                                        <?php if (empty($assigned_classes)): ?>
                                            <span class="text-muted fst-italic">None assigned</span>
                                        <?php else: ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($assigned_classes as $cls): ?>
                                                    <li><i class="bi bi-dot"></i> <?php echo htmlspecialchars($cls); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label"><i class="bi bi-book-half me-1"></i> Assigned Subjects</div>
                                    <div class="info-value mb-0">
                                        <?php if (empty($assigned_subjects)): ?>
                                            <span class="text-muted fst-italic">None assigned</span>
                                        <?php else: ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($assigned_subjects as $sub): ?>
                                                    <li><i class="bi bi-dot"></i> <?php echo htmlspecialchars($sub['subject_name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($sub['class_name']); ?>)</small></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
