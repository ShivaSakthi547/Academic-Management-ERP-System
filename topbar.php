<?php
// topbar.php
$username = $_SESSION['user_name'] ?? 'User';
?>
<div class="topbar d-flex justify-content-between align-items-center py-2 px-4 mb-4" style="margin: -24px -24px 24px -24px;">
    <div class="d-flex align-items-center">
        <a class="text-decoration-none text-dark fw-bold fs-5 d-flex align-items-center me-4" href="dashboard.php">
            <i class="bi bi-mortarboard-fill text-primary me-2 fs-4"></i>
            <span>Academic Management System</span>
        </a>
        <div class="d-none d-lg-flex align-items-center text-muted small fw-semibold bg-light rounded-pill px-3 py-1 border border-light">
            <i class="bi bi-clock me-2 text-primary"></i>
            <span id="liveClock"><?php echo date('D, M d, Y'); ?></span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Dropdown -->
        <?php if (($_SESSION['user_role'] ?? '') !== 'Admin'): ?>
        <div class="dropdown">
            <button class="btn btn-light rounded-circle position-relative p-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell fs-5 text-primary"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none border border-white" style="font-size:0.65rem; padding:0.25em 0.5em;" id="notificationBadge">
                    0
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 400px; overflow-y: auto; border-radius: 12px;" id="notificationList">
                <li class="px-3 py-2 border-bottom bg-light">
                    <h6 class="dropdown-header px-0 m-0 fw-bold text-dark">Notifications</h6>
                </li>
                <li id="noNotificationMsg"><span class="dropdown-item text-muted small py-3 text-center">No new notifications</span></li>
                <!-- Notifications will be injected here via JS -->
            </ul>
        </div>
        <?php endif; ?>

        <a href="institution_profile.php" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;" title="Institution Profile">
            <i class="bi bi-building fs-5 text-primary"></i>
        </a>
        
        <div class="d-flex align-items-center bg-white rounded-pill shadow-sm border border-light ps-2 pe-3 py-1">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px; font-weight:bold;">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div class="d-flex flex-column lh-1 me-3">
                <span class="small fw-bold text-dark">Welcome, <?php echo htmlspecialchars($username); ?></span>
                <span class="text-muted" style="font-size:0.7rem;"><?php echo $_SESSION['user_role'] ?? ''; ?></span>
            </div>
            <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="font-size:0.8rem; padding: 0.25rem 0.75rem;">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live Clock
    const clockEl = document.getElementById('liveClock');
    if(clockEl) {
        setInterval(() => {
            const now = new Date();
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            clockEl.textContent = now.toLocaleDateString('en-US', options);
        }, 1000);
    }
});
</script>

<?php if (($_SESSION['user_role'] ?? '') !== 'Admin'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function fetchNotifications() {
        fetch('notification_action.php?action=fetch_unread&ajax=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notificationBadge');
                    const list = document.getElementById('notificationList');
                    const noMsg = document.getElementById('noNotificationMsg');
                    
                    if (data.total_count > 0) {
                        if (data.count > 0) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.classList.remove('d-none');
                        } else {
                            badge.classList.add('d-none');
                        }
                        
                        list.innerHTML = '<li class="px-3 py-2 border-bottom bg-light"><h6 class="dropdown-header px-0 m-0 fw-bold text-dark">Notifications</h6></li>';
                        
                        data.notifications.forEach(notif => {
                            let sender = notif.sender_role === 'Admin' ? 'Management' : notif.sender_name;
                            let readStyle = notif.is_read == 0 ? 'fw-bold bg-light border-start border-3 border-primary' : '';
                            list.innerHTML += `
                                <li>
                                    <a class="dropdown-item border-bottom py-2 ${readStyle}" href="view_notification.php?id=${notif.id}">
                                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0 text-truncate" style="max-width: 180px; font-size:0.85rem;">${notif.title}</h6>
                                            <small class="text-muted" style="font-size: 0.65rem;">${new Date(notif.created_at).toLocaleDateString()}</small>
                                        </div>
                                        <p class="mb-0 small text-muted text-truncate" style="font-size:0.75rem;">From: ${sender}</p>
                                    </a>
                                </li>
                            `;
                        });
                        
                        if (data.total_count > 5) {
                            list.innerHTML += `<li><a class="dropdown-item text-center text-primary small fw-bold py-2 bg-light" href="all_notifications.php">View All Notifications</a></li>`;
                        }
                    } else {
                        badge.classList.add('d-none');
                        list.innerHTML = '<li class="px-3 py-2 border-bottom bg-light"><h6 class="dropdown-header px-0 m-0 fw-bold text-dark">Notifications</h6></li><li id="noNotificationMsg"><span class="dropdown-item text-muted small py-3 text-center">No new notifications</span></li>';
                    }
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    // Fetch on load and every 60 seconds
    fetchNotifications();
    setInterval(fetchNotifications, 60000);
});
</script>
<?php endif; ?>
