<?php
/**
 * Global Page Footer Template
 * 
 * 1. Closes the main content container.
 * 2. Renders the institutional copyright and branding.
 * 3. Injects essential scripts (Bootstrap JS, jsPDF, AutoTable).
 * 4. Ensures all pages have access to consistent frontend functionality.
 */
?>
</div> <!-- End container -->
<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container text-center">
        <span class="text-muted">&copy;
            <?php echo date('Y'); ?> SFIMS - Pamantasan ng Lungsod ng Muntinlupa
        </span>
    </div>
</footer>
<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jsPDF and AutoTable for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</body>

</html>