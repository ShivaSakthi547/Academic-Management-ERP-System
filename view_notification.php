<?php
// view_notification.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userId = $_SESSION['user_id'];
$notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notificationId <= 0) {
    header("Location: dashboard.php");
    exit();
}

try {
    // Fetch notification details
    $stmt = $pdo->prepare("
        SELECT n.*, u.username as sender_name, nr.is_read
        FROM notifications n
        JOIN notification_recipients nr ON n.id = nr.notification_id
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.id = ? AND nr.receiver_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Notification not found or access denied.'];
        header("Location: dashboard.php");
        exit();
    }

    // Mark as read if not already read
    if ($notification['is_read'] == 0) {
        $updateStmt = $pdo->prepare("UPDATE notification_recipients SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND receiver_id = ?");
        $updateStmt->execute([$notificationId, $userId]);
    }

} catch (PDOException $e) {
    die("Error fetching notification: " . $e->getMessage());
}

$page_title = "View Notification";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
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
                <h1 class="h2"><i class="bi bi-envelope-open-fill text-primary me-2"></i>Notification Details</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card p-4">
                        <h3 class="mb-3"><?= htmlspecialchars($notification['title']) ?></h3>
                        
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <div>
                                <span class="badge bg-primary text-uppercase me-2"><?= htmlspecialchars($notification['sender_role']) ?></span>
                                <span class="fw-bold text-muted">From: </span>
                                <span><?= $notification['sender_role'] === 'Admin' ? 'Management' : htmlspecialchars($notification['sender_name']) ?></span>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-clock-fill me-1"></i><?= date('F j, Y, g:i a', strtotime($notification['created_at'])) ?>
                            </div>
                        </div>

                        <div class="fs-5" style="white-space: pre-wrap; min-height: 150px;">
                            <?= nl2br(htmlspecialchars($notification['message'])) ?>
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
