<?php
// admin_form.php
session_start();
require_once __DIR__ . 
'/config/db.php';

// Protect page: only authenticated users can access
if (!isset($_SESSION[
'user_id'])) {
    $_SESSION[
'toast'] = [
'type' => 
'danger', 
'msg' => 
'Unauthenticated access. Please log in.'
];
    header("Location: login.php");
    exit();
}

$isEditMode = false;
$admin = [
'id' => 
'', 
'admin_id' => 
'', 
'admin_name' => 
'', 
'dob' => 
'', 
'address' => 
''
];
$formTitle = 
'Add New Admin';
$actionUrl = 
'action.php?action=add_admin';

// Check if in edit mode
if (isset($_GET[
'id'])) {
    $isEditMode = true;
    $adminId = intval($_GET[
'id']);
    $formTitle = 
'Edit Admin';
    $actionUrl = 
'action.php?action=edit_admin';

    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $fetchedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedAdmin) {
            $admin = $fetchedAdmin;
        } else {
            $_SESSION[
'toast'] = [
'type' => 
'danger', 
'msg' => 
'Admin not found.'
];
            header("Location: view_admins.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION[
'toast'] = [
'type' => 
'danger', 
'msg' => 
'Database error: ' . $e->getMessage()
];
        header("Location: view_admins.php");
        exit();
    }
}

// Handle form data from previous submission attempt
$formData = $_SESSION[
'form_data'] ?? $admin;
unset($_SESSION[
'form_data']);

$formErrors = $_SESSION[
'form_errors'] ?? [];
unset($_SESSION[
'form_errors']);

$toast = $_SESSION[
'toast'] ?? null;
unset($_SESSION[
'toast']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $formTitle; ?> - Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 
'Outfit', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .form-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .form-header h2 {
            font-weight: 600;
            color: #343a40;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: #86b7fe;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php include 'topbar.php'; ?>
                <div class="form-container">
                    <div class="form-header">
                        <h2><?php echo $formTitle; ?></h2>
                    </div>

                    <?php if (!empty($formErrors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($formErrors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($toast): ?>
                        <div class="alert alert-<?php echo $toast[
'type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($toast[
'msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo $actionUrl; ?>" method="POST" enctype="multipart/form-data">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($formData[
'id']); ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="admin_name" class="form-label">Admin Name</label>
                            <input type="text" class="form-control" id="admin_name" name="admin_name" value="<?php echo htmlspecialchars($formData[
'admin_name']); ?>" required>
                        </div>

                        <?php if ($isEditMode): ?>
                            <div class="mb-3">
                                <label for="admin_id" class="form-label">Admin ID</label>
                                <input type="text" class="form-control" id="admin_id" name="admin_id" value="<?php echo htmlspecialchars($formData[
'admin_id']); ?>" readonly>
                                <div class="form-text">Admin ID cannot be changed.</div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($formData[
'dob']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($formData['phone_number'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="major_subject" class="form-label">Major Subject</label>
                            <input type="text" class="form-control" id="major_subject" name="major_subject" value="<?php echo htmlspecialchars($formData['major_subject'] ?? ''); ?>">
                        </div>

                        <div class="mb-4">
                            <label for="photo" class="form-label">Profile Photo</label>
                            <input class="form-control" type="file" id="photo" name="photo" accept="image/png, image/jpeg, image/jpg">
                            <div class="form-text">Optional. Supported formats: JPG, JPEG, PNG.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i><?php echo $isEditMode ? 
'Update Admin' : 
'Add Admin'; ?>
                            </button>
                            <a href="view_admins.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>