<?php
// send_notification.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userRole = $_SESSION['user_role'] ?? '';
if ($userRole !== 'Admin' && $userRole !== 'Staff') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

// Fetch active students (users with role Student)
$students = [];
try {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'Student' AND is_active = 1 ORDER BY username ASC");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Failed to fetch students: " . $e->getMessage());
}

// Fetch active staff (for Admins only)
$staffList = [];
if ($userRole === 'Admin') {
    try {
        $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'Staff' AND is_active = 1 ORDER BY username ASC");
        $staffList = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Failed to fetch staff: " . $e->getMessage());
    }
}

$page_title = "Send Notification";
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
    <!-- Select2 for multi-select -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
                <h1 class="h2"><i class="bi bi-bell-fill text-primary me-2"></i>Send Notification</h1>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card p-4">
                        <form action="notification_action.php?action=send" method="POST" id="notificationForm">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label fw-bold">Notification Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required placeholder="e.g. Upcoming Holiday">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Target Students</label>
                                <select class="form-select select2-multiple" name="target_students[]" id="target_students" multiple="multiple" data-placeholder="Select students...">
                                    <option value="all">All Students</option>
                                    <option value="none">None</option>
                                    <?php foreach ($students as $stu): ?>
                                        <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($userRole === 'Admin'): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Target Staff</label>
                                <select class="form-select select2-multiple" name="target_staff[]" id="target_staff" multiple="multiple" data-placeholder="Select staff...">
                                    <option value="all">All Staff</option>
                                    <option value="none">None</option>
                                    <?php foreach ($staffList as $stf): ?>
                                        <option value="<?= $stf['id'] ?>"><?= htmlspecialchars($stf['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="message" class="form-label fw-bold">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="5" required placeholder="Type your message here..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Expiry Date</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="expiry_type" id="no_expiry" value="no_expiry" checked>
                                        <label class="form-check-label" for="no_expiry">No Expiry</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="expiry_type" id="add_expiry" value="add_expiry">
                                        <label class="form-check-label" for="add_expiry">Add Expiry Date</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 d-none" id="expiryDateContainer">
                                <label for="expiry_date" class="form-label fw-bold">Select Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" min="<?= date('Y-m-d') ?>">
                                <div class="form-text">Notification will disappear after this date.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="bi bi-send-fill me-2"></i>Send Notification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Toggle expiry date field
        $('input[name="expiry_type"]').change(function() {
            if ($(this).val() === 'add_expiry') {
                $('#expiryDateContainer').removeClass('d-none');
                $('#expiry_date').prop('required', true);
            } else {
                $('#expiryDateContainer').addClass('d-none');
                $('#expiry_date').prop('required', false).val('');
            }
        });

        // Form validation
        $('#notificationForm').submit(function(e) {
            let stuCount = $('#target_students').val();
            let stfCount = $('#target_staff').length ? $('#target_staff').val() : [];
            
            if ((!stuCount || stuCount.length === 0 || stuCount.includes('none')) && (!stfCount || stfCount.length === 0 || stfCount.includes('none'))) {
                e.preventDefault();
                alert('Please select at least one target audience (Student or Staff).');
            }
        });
        
        // Handle "All" selection logic for select2
        function handleAllSelection(selectId) {
            $(selectId).on('select2:select', function (e) {
                var data = e.params.data;
                if(data.id == 'all') {
                    $(selectId).val(['all']).trigger('change');
                } else if(data.id == 'none') {
                    $(selectId).val(['none']).trigger('change');
                } else {
                    var currentVal = $(selectId).val();
                    if(currentVal.includes('all')) {
                        var index = currentVal.indexOf('all');
                        if (index > -1) { currentVal.splice(index, 1); }
                        $(selectId).val(currentVal).trigger('change');
                    }
                    if(currentVal.includes('none')) {
                        var index = currentVal.indexOf('none');
                        if (index > -1) { currentVal.splice(index, 1); }
                        $(selectId).val(currentVal).trigger('change');
                    }
                }
            });
        }
        
        handleAllSelection('#target_students');
        if ($('#target_staff').length) {
            handleAllSelection('#target_staff');
        }
    });
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
