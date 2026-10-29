<?php
// hall_management.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$action = $_POST['action'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create_hall') {
            $hall_name = $_POST['hall_name'];
            $hall_code = $_POST['hall_code'];
            $capacity = $_POST['capacity'];

            $stmt = $pdo->prepare("INSERT INTO exam_halls (hall_name, hall_code, capacity) VALUES (?, ?, ?)");
            $stmt->execute([$hall_name, $hall_code, $capacity]);
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Hall created successfully!'];
            header("Location: hall_management.php");
            exit();

        } elseif ($action === 'edit_hall') {
            $hall_id = $_POST['hall_id'];
            $hall_name = $_POST['hall_name'];
            $hall_code = $_POST['hall_code'];
            $capacity = $_POST['capacity'];
            $status = isset($_POST['status']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE exam_halls SET hall_name = ?, hall_code = ?, capacity = ?, status = ? WHERE id = ?");
            $stmt->execute([$hall_name, $hall_code, $capacity, $status, $hall_id]);
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Hall updated successfully!'];
            header("Location: hall_management.php");
            exit();
        } elseif ($action === 'delete_hall') {
            $hall_id = $_POST['hall_id'];
            $stmt = $pdo->prepare("DELETE FROM exam_halls WHERE id = ?");
            $stmt->execute([$hall_id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Hall deleted successfully!'];
            header("Location: hall_management.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch all halls
try {
    $stmt = $pdo->query("SELECT * FROM exam_halls ORDER BY hall_name");
    $halls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall Management - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .hall-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); transition: all 0.2s ease-in-out; border-top: 5px solid #6f42c1; }
        .hall-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(111, 66, 193, 0.15); }
        .capacity-badge { background-color: #e2d9f3; color: #6f42c1; font-weight: 600; padding: 5px 10px; border-radius: 8px;}
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
                <h1 class="h2"><i class="bi bi-building me-2 text-primary"></i>Examination Halls</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createHallModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Hall
                    </button>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php if (empty($halls)): ?>
                    <div class="col-12">
                        <div class="alert alert-info border-0 shadow-sm rounded-4 text-center py-5">
                            <div class="fs-1 text-secondary mb-3"><i class="bi bi-building-x"></i></div>
                            <h5>No halls configured yet.</h5>
                            <p class="text-muted">Click the Add Hall button to create your first examination hall.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($halls as $hall): ?>
                        <div class="col">
                            <div class="card hall-card p-4 h-100 position-relative">
                                <?php if (!$hall['status']): ?>
                                    <div class="position-absolute top-0 end-0 m-3 badge bg-danger rounded-pill">Inactive</div>
                                <?php endif; ?>
                                <div class="text-center mb-3">
                                    <div class="fs-1 text-primary opacity-50"><i class="bi bi-door-open-fill"></i></div>
                                </div>
                                <h4 class="fw-bold text-center mb-1"><?php echo htmlspecialchars($hall['hall_name']); ?></h4>
                                <p class="text-muted text-center small mb-3">Code: <?php echo htmlspecialchars($hall['hall_code']); ?></p>
                                
                                <div class="d-flex justify-content-center mb-4">
                                    <span class="capacity-badge"><i class="bi bi-people-fill me-1"></i> Capacity: <?php echo htmlspecialchars($hall['capacity']); ?></span>
                                </div>

                                <div class="d-flex gap-2 justify-content-center mt-auto border-top pt-3">
                                    <button class="btn btn-sm btn-outline-primary w-50" data-bs-toggle="modal" data-bs-target="#editHallModal<?php echo $hall['id']; ?>"><i class="bi bi-pencil me-1"></i> Edit</button>
                                    <form method="POST" action="" class="w-50 m-0" onsubmit="return confirm('Delete this hall? Make sure it is not used in active allotments.');">
                                        <input type="hidden" name="action" value="delete_hall">
                                        <input type="hidden" name="hall_id" value="<?php echo $hall['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash me-1"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Hall Modal -->
                        <div class="modal fade" id="editHallModal<?php echo $hall['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Edit Hall</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit_hall">
                                        <input type="hidden" name="hall_id" value="<?php echo $hall['id']; ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Hall Name</label>
                                                <input type="text" class="form-control" name="hall_name" value="<?php echo htmlspecialchars($hall['hall_name']); ?>" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Hall Code</label>
                                                    <input type="text" class="form-control" name="hall_code" value="<?php echo htmlspecialchars($hall['hall_code']); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Capacity</label>
                                                    <input type="number" class="form-control" name="capacity" min="1" value="<?php echo htmlspecialchars($hall['capacity']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3 form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="status" id="statusSwitch<?php echo $hall['id']; ?>" <?php echo $hall['status'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold" for="statusSwitch<?php echo $hall['id']; ?>">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Create Hall Modal -->
<div class="modal fade" id="createHallModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Examination Hall</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_hall">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Hall Name</label>
                        <input type="text" class="form-control" name="hall_name" required placeholder="e.g. Main Auditorium">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hall Code</label>
                            <input type="text" class="form-control" name="hall_code" required placeholder="e.g. AUD-01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Capacity</label>
                            <input type="number" class="form-control" name="capacity" min="1" required placeholder="e.g. 100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Hall</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
