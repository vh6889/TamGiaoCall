</main> <footer class="text-center p-3 text-muted">
                <small>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All Rights Reserved.</small>
            </footer>

        </div> </div> <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="appToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Thông báo</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody">
                </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <script>
        /**
         * Shows a toast notification.
         * @param {string} message The message to display.
         * @param {string} type 'success', 'error', 'info', 'warning'.
         */
        function showToast(message, type = 'info') {
            const toastEl = $('#appToast');
            const toastBody = $('#toastBody');
            const toastTitle = $('#toastTitle');

            // Reset classes
            toastEl.removeClass('bg-success bg-danger bg-warning bg-info text-white');
            
            let title = 'Thông báo';
            let bgClass = 'bg-info';

            switch (type) {
                case 'success':
                    title = 'Thành công!';
                    bgClass = 'bg-success';
                    break;
                case 'error':
                    title = 'Có lỗi!';
                    bgClass = 'bg-danger';
                    break;
                case 'warning':
                    title = 'Cảnh báo';
                    bgClass = 'bg-warning';
                    break;
            }

            toastTitle.text(title);
            toastBody.text(message);
            toastEl.addClass(bgClass + ' text-white');

            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }

        // Example: show a welcome message on page load if a flash message exists in session (optional)
        // This part requires coordination with PHP flash messages if you want to use it.
        <?php
        $flash = get_flash();
        if ($flash) {
            echo "showToast('" . addslashes($flash['message']) . "', '" . $flash['type'] . "');";
        }
        ?>
    </script>

</body>
</html>