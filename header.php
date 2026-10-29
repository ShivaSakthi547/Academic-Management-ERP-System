<?php
// header.php
// This file assumes session_start() has been called in the including file.
// It also assumes $_SESSION['user_name'] and $_SESSION['user_role'] are set if a user is logged in.

// Basic check for session variables, though the including page should handle full authentication
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : 'Guest';
$user_role = $is_logged_in ? htmlspecialchars($_SESSION['user_role']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .navbar-custom {
            background-color: #0d6efd;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        }
        .navbar-custom .nav-link, .navbar-custom .navbar-brand {
            color: #ffffff;
        }
        .navbar-custom .nav-link:hover {
            color: rgba(255, 255, 255, 0.75);
        }
        .btn-outline-light {
            border-color: #ffffff;
            color: #ffffff;
        }
        .btn-outline-light:hover {
            background-color: #ffffff;
            color: #0d6efd;
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm py-3 mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2 fs-3"></i>
            <span>Academic Management System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <?php if ($is_logged_in): ?>
                    <li class="nav-item me-3 text-white">
                        <span class="d-inline-flex align-items-center">
                            <i class="bi bi-person-circle me-2 fs-5"></i>
                            Welcome, <strong class="ms-1"><?php echo $user_name; ?></strong> 
                            <span class="badge bg-white text-primary ms-2"><?php echo $user_role; ?></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm px-3 fw-bold" href="action.php?action=logout">
                            <i class="bi bi-box-arrow-left me-1"></i>Logout
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>