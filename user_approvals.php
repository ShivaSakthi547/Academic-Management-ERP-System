<?php
// user_approvals.php
session_start();
require_once __DIR__ . '/config/db.php';

// Check if user is logged in and is Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$status = $_GET['status'] ?? 'Pending';
if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
    $status = 'Pending';
}

// Fetch users by status (excluding Students)
$stmt = $pdo->prepare("SELECT * FROM users WHERE status = ? AND role != 'Student' ORDER BY created_at DESC");
$stmt->execute([$status]);
$users = $stmt->fetchAll();

$pageTitle = "$status Users - Academic Management System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Export Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
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
                <h1 class="h2"><?php echo htmlspecialchars($status); ?> Users</h1>
                <div class="btn-toolbar mb-2 mb-md-0 gap-2 flex-wrap">
                    <button onclick="exportToExcel('exportTable','users_data')" class="btn btn-sm btn-success" title="Download Excel">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                    </button>
                    <button onclick="exportToPDF('exportTable','users_data','Users List')" class="btn btn-sm btn-danger" title="Download PDF">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                    </button>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table id="exportTable" class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Date of Registration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%;">
                                                        <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($user['address'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($status === 'Pending'): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="action.php?action=approve_user&id=<?php echo $user['id']; ?>" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this user?');">
                                                            <i class="bi bi-check-circle me-1"></i> Approve
                                                        </a>
                                                        <a href="action.php?action=reject_user&id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this user?');">
                                                            <i class="bi bi-x-circle me-1"></i> Reject
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $status === 'Approved' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $status; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            No <?php echo strtolower($status); ?> users found.
                                        </td>
                                    </tr>
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
<script src="includes/export_utils.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
