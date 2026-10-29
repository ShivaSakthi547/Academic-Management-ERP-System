<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
$filter_param = $_GET['filter'] ?? '';
?>
<div class="bg-dark text-white border-end d-flex flex-column flex-shrink-0" id="sidebar-wrapper" style="width: 250px; min-height: calc(100vh - 72px); z-index: 99;">
    <div class="list-group list-group-flush my-3 w-100">
        <!-- Dashboard Link -->
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'dashboard.php' && $filter_param !== 'all') ? 'active bg-primary fw-bold text-white' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
        
        <!-- Students Link -->
        <a href="dashboard.php?filter=all" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'dashboard.php' && $filter_param === 'all') ? 'active bg-primary fw-bold text-white' : ''; ?>">
            <i class="bi bi-people-fill me-2"></i>Students
        </a>

        <?php if ($role === 'Admin'): ?>
            <div class="text-uppercase text-secondary px-4 mt-4 mb-2 small fw-bold tracking-wider">Admin Actions</div>
            
            <!-- Add Admin -->
            <a href="admin_form.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'admin_form.php' && !isset($_GET['id'])) ? 'active bg-primary fw-bold text-white' : ''; ?>">
                <i class="bi bi-person-plus-fill me-2"></i>Add Admin
            </a>
            
            <!-- View Admins -->
            <a href="view_admins.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'view_admins.php') ? 'active bg-primary fw-bold text-white' : ''; ?>">
                <i class="bi bi-shield-check me-2"></i>View Admins
            </a>
            
            <!-- Add Staff -->
            <a href="staff_form.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'staff_form.php' && !isset($_GET['id'])) ? 'active bg-primary fw-bold text-white' : ''; ?>">
                <i class="bi bi-person-plus-fill me-2"></i>Add Staff
            </a>
            
            <!-- View Staff -->
            <a href="view_staff.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3 px-4 <?php echo ($current_page === 'view_staff.php') ? 'active bg-primary fw-bold text-white' : ''; ?>">
                <i class="bi bi-people me-2"></i>View Staff
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
/* Custom style to override Bootstrap list-group active state in dark theme */
#sidebar-wrapper .list-group-item-action:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
#sidebar-wrapper .list-group-item-action.active {
    background-color: #0d6efd !important;
    color: #ffffff !important;
}
.tracking-wider {
    letter-spacing: 0.05em;
}
</style>
