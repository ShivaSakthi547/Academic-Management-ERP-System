<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm py-3 sticky-top w-100">
    <div class="container-fluid px-4">
        <!-- Logo and Title on the Left -->
        <a class="navbar-brand d-flex align-items-center fw-bold text-white text-decoration-none" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2 fs-3"></i>
            <span>Academic Management System</span>
        </a>

        <!-- Dynamic Page Title (Optional) -->
        <?php if (isset($page_title)): ?>
            <span class="navbar-text d-none d-md-inline-block ms-4 text-white-50 fs-5 fw-semibold"><?php echo htmlspecialchars($page_title); ?></span>
        <?php endif; ?>

        <!-- Hamburger Toggler for Mobile -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Right Side: Welcome Message & Logout Button -->
        <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center mt-3 mt-lg-0 gap-3">
                <span class="text-white d-inline-flex align-items-center">
                    <i class="bi bi-person-circle me-2 fs-5"></i>
                    Welcome, <strong class="ms-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> 
                    <span class="badge bg-white text-primary ms-2"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span>
                </span>
                <button type="button" class="btn btn-outline-light btn-sm px-3 fw-bold align-self-start align-self-lg-center" onclick="showLogoutConfirm()">
                    <i class="bi bi-box-arrow-left me-1"></i>Logout
                </button>
            </div>
        </div>
    </div>
</nav>
