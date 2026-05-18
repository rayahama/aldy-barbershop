<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- Chart.js (hanya owner) -->
<?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>
<!-- Custom JS -->
<script src="../assets/js/script.js"></script>

<?php if (isset($flash)): ?>
<script>
    // Auto-hide flash toast setelah 4 detik
    setTimeout(function() {
        const toast = document.getElementById('flashToast');
        if (toast) {
            const bsToast = bootstrap.Toast.getInstance(toast.querySelector('.toast'));
            if (bsToast) bsToast.hide();
            else toast.remove();
        }
    }, 4000);
</script>
<?php endif; ?>

</body>
</html>