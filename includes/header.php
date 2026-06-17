<?php
/**
 * Main Layout Header Wrapper
 * Smart Agricultural Decision Support System
 */
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " - Agriculture DSS" : "Agriculture DSS"; ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="assets/css/custom_style.css">
</head>
<body>

<!-- Backdrop Overlay for Mobile Sidebar -->
<div id="sidebar-overlay"></div>

<div id="wrapper">
    <!-- Include Sidebar -->
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div id="content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-custom">
            <div class="container-fluid p-0 d-flex justify-content-between align-items-center">
                <!-- Sidebar Toggle & Title Group -->
                <div class="d-flex align-items-center">
                    <button type="button" id="sidebarCollapse" class="btn btn-link text-dark me-2 p-1 border-0" aria-label="Toggle Sidebar">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <!-- Dynamic Page Title -->
                    <span class="page-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : "Dashboard"; ?></span>
                </div>
                
                <!-- Profile Dropdown (Persistent on all viewports, showing only profile icon) -->
                <div class="dropdown">
                    <a class="text-dark p-1 d-flex align-items-center text-decoration-none" href="#" id="userProfileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="outline: none; box-shadow: none;">
                        <i class="bi bi-person-circle fs-4 text-muted hover-primary"></i> 
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border shadow-none py-2" aria-labelledby="userProfileDropdown" style="border-radius: var(--radius-subtle); min-width: 200px;">
                        <!-- User info displayed inside dropdown -->
                        <li class="px-3 py-2">
                            <span class="d-block small text-muted" style="font-size: 0.75rem;">Signed in as</span>
                            <span class="d-block fw-semibold text-dark text-truncate" style="font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <span class="badge bg-secondary fw-normal mt-1" style="font-size: 0.7rem; background-color: var(--primary-color) !important;">
                                <?php echo htmlspecialchars($_SESSION['role']); ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger small" href="actions/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Log Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Page Main Content Container -->
        <div class="container-fluid p-4">
