<?php
// includes/footer.php
?>
<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title fw-bold" id="logoutConfirmModalLabel"><i class="bi bi-box-arrow-right me-2"></i>Confirm Logout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4 text-center">
        <p class="fs-5 mb-0 text-dark">Are you sure you want to logout?</p>
      </div>
      <div class="modal-footer border-0 bg-light d-flex justify-content-center">
        <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">No</button>
        <a href="logout.php" class="btn btn-danger px-4 py-2 fw-semibold">Yes</a>
      </div>
    </div>
  </div>
</div>

<!-- Dynamic Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
    <?php 
    $toasts_to_show = [];
    if (isset($_SESSION['toasts'])) {
        $toasts_to_show = $_SESSION['toasts'];
        unset($_SESSION['toasts']);
    } elseif (isset($_SESSION['toast'])) {
        $toasts_to_show[] = $_SESSION['toast'];
        unset($_SESSION['toast']);
    }
    
    foreach ($toasts_to_show as $index => $t): 
    ?>
        <div id="liveToast_<?php echo $index; ?>" class="toast align-items-center text-white <?php echo ($t['type'] === 'success') ? 'bg-success' : 'bg-danger'; ?> border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi <?php echo ($t['type'] === 'success') ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill'; ?> me-2"></i>
                    <?php echo htmlspecialchars($t['msg']); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let logoutConfirmModal;
    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('logoutConfirmModal');
        if (modalEl) {
            logoutConfirmModal = new bootstrap.Modal(modalEl);
        }
        
        // Show all rendered toasts
        <?php foreach ($toasts_to_show as $index => $t): ?>
            const toastEl_<?php echo $index; ?> = document.getElementById('liveToast_<?php echo $index; ?>');
            if (toastEl_<?php echo $index; ?>) {
                const toast = new bootstrap.Toast(toastEl_<?php echo $index; ?>);
                toast.show();
            }
        <?php endforeach; ?>
    });

    function showLogoutConfirm() {
        if (logoutConfirmModal) {
            logoutConfirmModal.show();
        } else {
            if (confirm("Are you sure you want to logout?")) {
                window.location.href = "logout.php";
            }
        }
    }
</script>
</body>
</html>
