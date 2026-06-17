<?php
/**
 * System Logs Page - Paginated Audit Trail
 * Smart Agricultural Decision Support System
 */
$page_title = "System Logs";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

// Setup pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;

try {
    // 1. Get total logs count
    $countStmt = $pdo->query("SELECT COUNT(*) FROM tbl_system_logs");
    $total_records = intval($countStmt->fetchColumn());
} catch (\PDOException $e) {
    error_log("Database count error in system_logs: " . $e->getMessage());
    $total_records = 0;
}

$total_pages = ceil($total_records / $limit);
$page = max(1, min($total_pages, $page));
$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

try {
    // 2. Fetch logs for the current page
    // Left Join to keep logs where user_id is NULL (failed login attempts)
    $stmt = $pdo->prepare("
        SELECT l.id, l.action, l.created_at, u.username, u.role 
        FROM tbl_system_logs l
        LEFT JOIN tbl_users u ON l.user_id = u.id
        ORDER BY l.id DESC
        LIMIT :limit OFFSET :offset
    ");
    // Bind parameters as integers to prevent PDO emulate prepare type mapping issues
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Database read error in system_logs: " . $e->getMessage());
    $logs = [];
}
?>

<div class="row g-4">
    <!-- Audit Trail Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-lock me-2 text-success" style="color: #1b5e20 !important;"></i>System Audit Trail Logs
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Timestamp</th>
                                <th style="width: 150px;">User</th>
                                <th style="width: 150px;">Role</th>
                                <th>Action Performed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <i class="bi bi-card-text d-block fs-3 mb-2"></i>
                                        No system logs recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): 
                                    // Highlight certain terms inside actions
                                    $action_text = htmlspecialchars($log['action']);
                                    $action_text = str_replace(
                                        ['Successful login.', 'User logged out.', 'Failed login attempt'],
                                        ['<span class="text-success fw-semibold">Successful login.</span>', '<span class="text-muted">User logged out.</span>', '<span class="text-danger fw-semibold">Failed login attempt</span>'],
                                        $action_text
                                    );
                                ?>
                                    <tr>
                                        <td class="font-monospace text-muted" style="font-size: 0.82rem;">
                                            <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?>
                                        </td>
                                        <td>
                                            <?php if ($log['username'] !== null): ?>
                                                <span class="fw-semibold text-dark"><i class="bi bi-person-fill me-1 text-muted"></i><?php echo htmlspecialchars($log['username']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted font-italic"><i class="bi bi-shield-slash me-1"></i>Guest / System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['role'] !== null): ?>
                                                <?php if ($log['role'] === 'System Admin'): ?>
                                                    <span class="badge bg-danger fw-normal" style="font-size: 0.75rem; background-color: #dc3545 !important;">System Admin</span>
                                                <?php elseif ($log['role'] === 'DA Officer'): ?>
                                                    <span class="badge bg-primary fw-normal" style="font-size: 0.75rem; background-color: #0d6efd !important;">DA Officer</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary fw-normal" style="font-size: 0.75rem; background-color: #6c757d !important;">Extension Worker</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $action_text; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination Footer -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                    <span class="small text-muted">
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_records); ?> total audit logs)
                    </span>
                    <nav aria-label="Logs navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <!-- Numbered Pages (Show local window of max 5 pages) -->
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo ($page === $i) ? 'bg-primary border-primary text-white' : ''; ?>" style="<?php echo ($page === $i) ? 'background-color: var(--primary-color) !important; border-color: var(--primary-color) !important;' : ''; ?>" href="?page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <!-- Next Page -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
