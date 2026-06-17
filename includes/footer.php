<?php
/**
 * Main Layout Footer Wrapper
 * Smart Agricultural Decision Support System
 */
?>
        </div> <!-- End container-fluid container -->
    </div> <!-- End content wrapper -->
</div> <!-- End wrapper -->

<!-- Bootstrap 5 JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Sidebar Mobile Toggle Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const sidebarCollapse = document.getElementById("sidebarCollapse");
    const sidebarOverlay = document.getElementById("sidebar-overlay");

    function toggleSidebar() {
        sidebar.classList.toggle("active");
        sidebarOverlay.classList.toggle("active");
    }

    if (sidebarCollapse && sidebar && sidebarOverlay) {
        sidebarCollapse.addEventListener("click", toggleSidebar);
        sidebarOverlay.addEventListener("click", toggleSidebar);
    }
});
</script>
</body>
</html>
