<?php 
$toast_data = null;
if (isset($toast) && is_array($toast)) {
    $toast_data = $toast;
} elseif (isset($_SESSION['toast']) && is_array($_SESSION['toast'])) {
    $toast_data = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

if ($toast_data): 
    $toast_type = $toast_data['type'] ?? 'info';
    $toast_msg = $toast_data['msg'] ?? '';
    $bg_class = ($toast_type === 'success') ? 'bg-success' : (($toast_type === 'danger' || $toast_type === 'error') ? 'bg-danger' : 'bg-primary');
    $icon_class = ($toast_type === 'success') ? 'bi-check-circle-fill' : (($toast_type === 'danger' || $toast_type === 'error') ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill');
?>
    <!-- Reusable Toast Notification Container -->
    <div class="toast-container position-fixed top-0 end-0 p-4" style="z-index: 1100;">
        <div id="liveToast" class="toast align-items-center bg-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000" style="border-radius: 12px; border-left: 4px solid var(--bs-<?php echo $toast_type === 'error' ? 'danger' : $toast_type; ?>) !important; overflow: hidden;">
            <div class="d-flex w-100">
                <div class="toast-body fw-semibold fs-6 d-flex align-items-center text-dark py-3 w-100">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3 <?php echo $bg_class; ?> text-white" style="width:32px; height:32px; flex-shrink:0;">
                        <i class="bi <?php echo $icon_class; ?> fs-6"></i>
                    </div>
                    <span style="flex-grow:1;"><?php echo htmlspecialchars($toast_msg); ?></span>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <!-- Progress bar animation -->
            <div style="height:3px; width:100%; background-color:#f1f5f9;">
                <div class="<?php echo $bg_class; ?>" style="height:100%; width:100%; animation: toastProgress 4s linear forwards;"></div>
            </div>
            <style>
                @keyframes toastProgress {
                    from { width: 100%; }
                    to { width: 0%; }
                }
            </style>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toastEl = document.getElementById('liveToast');
            if (toastEl) {
                // Ensure bootstrap is loaded
                if (typeof bootstrap !== 'undefined') {
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                } else {
                    // Fallback polling if script loads late
                    let checkInterval = setInterval(() => {
                        if (typeof bootstrap !== 'undefined') {
                            clearInterval(checkInterval);
                            const toast = new bootstrap.Toast(toastEl);
                            toast.show();
                        }
                    }, 100);
                    setTimeout(() => clearInterval(checkInterval), 3000);
                }
            }
        });
    </script>
<?php endif; ?>
