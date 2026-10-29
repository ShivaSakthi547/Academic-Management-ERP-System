<?php
// email_logs.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

// Pagination variables
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter and Search
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(recipient_email LIKE ? OR subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

try {
    // Get total count for pagination
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE $where_sql");
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Get records
    $query = "SELECT * FROM email_logs WHERE $where_sql ORDER BY sent_time DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Stats
    $stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM email_logs GROUP BY status");
    $stats_raw = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
    $stats = ['Sent' => 0, 'Failed' => 0];
    $total_emails = 0;
    foreach ($stats_raw as $row) {
        if ($row['status'] == 'Sent') $stats['Sent'] = $row['count'];
        elseif ($row['status'] == 'Failed') $stats['Failed'] = $row['count'];
        $total_emails += $row['count'];
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
    <title>Email Logs - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 24px; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .log-row-Failed { background-color: rgba(220, 53, 69, 0.05); }
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
                <h1 class="h2"><i class="bi bi-envelope-paper-fill text-primary me-2"></i>Email Delivery Logs</h1>
            </div>

            <!-- Stats Overview -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1 opacity-75">Total Emails</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo number_format($total_emails); ?></h2>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-envelope"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1 opacity-75">Delivered Successfully</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['Sent']); ?></h2>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-check-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1 opacity-75">Delivery Failed</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['Failed']); ?></h2>
                                </div>
                                <div class="fs-1 opacity-50"><i class="bi bi-x-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card p-3 mb-4">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Recipient Email or Subject..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="Sent" <?php echo $status_filter === 'Sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="Failed" <?php echo $status_filter === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Filter</button>
                        <a href="email_logs.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Clear</a>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="card p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sent Time</th>
                                <th>Recipient</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No email logs found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="log-row-<?php echo htmlspecialchars($log['status']); ?>">
                                        <td><span class="text-nowrap"><i class="bi bi-clock me-1 text-muted"></i><?php echo date('d M Y, h:i A', strtotime($log['sent_time'])); ?></span></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                        <td>
                                            <?php if ($log['status'] === 'Sent'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-lg me-1"></i>Sent</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-x-lg me-1"></i>Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['status'] === 'Failed' && !empty($log['error_message'])): ?>
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#errorModal<?php echo $log['id']; ?>">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>View Error
                                                </button>
                                                
                                                <!-- Error Modal -->
                                                <div class="modal fade" id="errorModal<?php echo $log['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delivery Error</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="mb-1 fw-bold">To: <span class="fw-normal"><?php echo htmlspecialchars($log['recipient_email']); ?></span></p>
                                                                <p class="mb-3 fw-bold">Subject: <span class="fw-normal"><?php echo htmlspecialchars($log['subject']); ?></span></p>
                                                                <div class="alert alert-danger mb-0">
                                                                    <strong>Error Details:</strong><br>
                                                                    <?php echo nl2br(htmlspecialchars($log['error_message'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
