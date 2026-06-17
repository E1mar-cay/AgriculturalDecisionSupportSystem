<?php
/**
 * Navigation Sidebar Component - Bottom Pinned Log Out
 * Smart Agricultural Decision Support System
 */

// Determine the current file basename to set active state
$current_script = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="d-flex flex-column justify-content-between">
    <div>
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <i class="bi bi-tree-fill me-2" style="color: #81c784; font-size: 1.25rem;"></i>
                <span>AGRICULTURE DSS</span>
            </div>
        </div>
        
        <ul class="components mb-0">
            <li class="<?php echo ($current_script === 'dashboard.php') ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo ($current_script === 'data_management.php') ? 'active' : ''; ?>">
                <a href="data_management.php">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Data Management</span>
                </a>
            </li>
            <li class="<?php echo ($current_script === 'forecast_rules.php') ? 'active' : ''; ?>">
                <a href="forecast_rules.php">
                    <i class="bi bi-sliders2-vertical"></i>
                    <span>Forecast Rules</span>
                </a>
            </li>
            <li class="<?php echo ($current_script === 'resource_allocation.php') ? 'active' : ''; ?>">
                <a href="resource_allocation.php">
                    <i class="bi bi-bar-chart-steps"></i>
                    <span>Resource Allocation</span>
                </a>
            </li>
            <li class="<?php echo ($current_script === 'system_logs.php') ? 'active' : ''; ?>">
                <a href="system_logs.php">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>System Logs</span>
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'System Admin'): ?>
            <li class="<?php echo ($current_script === 'users.php') ? 'active' : ''; ?>">
                <a href="users.php">
                    <i class="bi bi-people-fill"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li class="<?php echo ($current_script === 'settings.php') ? 'active' : ''; ?>">
                <a href="settings.php">
                    <i class="bi bi-gear-fill"></i>
                    <span>System Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="border-top" style="border-color: rgba(255, 255, 255, 0.08) !important; padding: 0.5rem 0;">
        <ul class="components my-0 py-0" style="list-style: none; padding-left: 0;">
            <li>
                <a href="actions/logout.php" class="py-3">
                    <i class="bi bi-box-arrow-right text-danger"></i>
                    <span class="text-danger">Log Out</span>
                </a>
            </li>
        </ul>
    </div>
</div>
