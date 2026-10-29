<?php
// view_admin.php
session_start();

// Protect page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

// Check if the user has Admin role
if ($_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can view this page.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$adminId = intval($_GET['id'] ?? 0);

if (!$adminId) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Invalid Admin ID.'];
    header("Location: view_admins.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Record not found.'];
        header("Location: view_admins.php");
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
    <title>View Admin - Academic Management System</title>
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
        .admin-profile-img { 
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
                <h1 class="h2">Admin Details</h1>
                <div>
                    <a href="view_admins.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to List</a>
                    <a href="admin_form.php?id=<?php echo $admin['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-fill"></i> Edit Profile</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card profile-card">
                        <div class="profile-header">
                            <div class="profile-img-container mb-3">
                                <img src="<?php echo htmlspecialchars($admin['photo']); ?>" alt="Profile" class="admin-profile-img" onerror="this.src='uploads/default_avatar.png'">
                            </div>
                            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($admin['admin_name']); ?></h3>
                            <p class="text-secondary mb-2">ID: <?php echo htmlspecialchars($admin['admin_id']); ?></p>
                            <span class="badge <?php echo ($admin['status'] == 1) ? 'bg-success' : 'bg-danger'; ?> fs-6 rounded-pill px-3 py-2">
                                <?php echo ($admin['status'] == 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card detail-card p-4">
                        <h5 class="border-bottom pb-3 mb-4 fw-bold text-primary"><i class="bi bi-person-badge-fill me-2"></i>Admin Information</h5>
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-person me-1"></i> Admin Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['admin_name']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-hash me-1"></i> Admin ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['admin_id']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-calendar-date me-1"></i> Date of Birth</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($admin['dob'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-shield-lock me-1"></i> Role</div>
                                <div class="info-value"><span class="badge bg-primary fs-6">Admin</span></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-house-door me-1"></i> Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($admin['address'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-envelope me-1"></i> Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['email']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-telephone me-1"></i> Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['phone_number']); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label"><i class="bi bi-book me-1"></i> Major Subject</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['major_subject']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-clock-history me-1"></i> Created Date</div>
                                <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($admin['created_at'])); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label"><i class="bi bi-activity me-1"></i> Status</div>
                                <div class="info-value">
                                    <?php if ($admin['status'] == 1): ?>
                                        <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </div>
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
