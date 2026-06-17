<?php
/**
 * User Management Page - Secure Admin CRUD
 * Smart Agricultural Decision Support System
 */
$page_title = "User Management";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

// Authorization check: only System Admin allowed
if ($_SESSION['role'] !== 'System Admin') {
    echo '
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger py-3">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <span class="fw-semibold">Access Denied:</span> You do not have permission to view or manage system user accounts.
            </div>
            <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-house-door me-1"></i>Return to Dashboard</a>
        </div>
    </div>';
    include_once __DIR__ . '/includes/footer.php';
    exit();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user count for stats card
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM tbl_users");
    $total_users = intval($countStmt->fetchColumn());
} catch (\PDOException $e) {
    error_log("Database count error in users: " . $e->getMessage());
    $total_users = 0;
}

// Parse search and page params
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clause = '';
$params = [];
if ($search !== '') {
    $where_clause = 'WHERE username LIKE :search';
    $params['search'] = '%' . $search . '%';
}

// Calculate total filtered users and pages
try {
    $filteredCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users $where_clause");
    $filteredCountStmt->execute($params);
    $filtered_users = intval($filteredCountStmt->fetchColumn());
} catch (\PDOException $e) {
    error_log("Database count error in users filter: " . $e->getMessage());
    $filtered_users = 0;
}

$limit = 10;
$total_pages = ceil($filtered_users / $limit);
$page = max(1, min($total_pages, $page));
$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

// Retrieve users
try {
    $dataStmt = $pdo->prepare("SELECT id, username, role, created_at FROM tbl_users $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $dataStmt->execute($params);
    $users = $dataStmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Database query error in users list: " . $e->getMessage());
    $users = [];
}
?>

<!-- SweetAlert2 CSS & JS CDNs -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Row 1: Metrics Overview and Filters Bar -->
<div class="row g-4 mb-4">
    <!-- Quick Stats -->
    <div class="col-md-3">
        <div class="card p-3 h-100 justify-content-center">
            <span class="text-muted small fw-semibold uppercase mb-1">Total System Users</span>
            <h3 class="mb-0 fw-bold" style="color: #1b5e20;"><?php echo number_format($total_users); ?></h3>
        </div>
    </div>
    
    <!-- Search panel -->
    <div class="col-md-9">
        <div class="card p-3">
            <form method="GET" action="users.php" class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label for="search" class="form-label small fw-semibold text-muted mb-1">Search Username</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="search" class="form-control form-control-sm border-start-0" placeholder="Type a username..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-filter"></i> Search
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="users.php" class="btn btn-sm btn-outline-secondary" title="Clear Search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Column: User list grid -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2" style="color: #1b5e20;"></i>System Accounts</span>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1"></i>Add Account
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Username</th>
                            <th>System Role</th>
                            <th>Created Date</th>
                            <th class="text-end" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-person-slash d-block fs-3 mb-2"></i>
                                    <span>No system accounts found matching criteria.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php $is_self = (intval($user['id']) === intval($_SESSION['user_id'])); ?>
                                <tr id="user-row-<?php echo $user['id']; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle text-muted me-2 fs-5"></i>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($user['username']); ?></span>
                                            <?php if ($is_self): ?>
                                                <span class="badge bg-success ms-2" style="font-size: 0.65rem; background-color: var(--primary-color) !important;">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'System Admin'): ?>
                                            <span class="badge bg-danger fw-normal" style="font-size: 0.8rem; background-color: #dc3545 !important;">System Admin</span>
                                        <?php elseif ($user['role'] === 'DA Officer'): ?>
                                            <span class="badge bg-primary fw-normal" style="font-size: 0.8rem; background-color: #0d6efd !important;">DA Officer</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary fw-normal" style="font-size: 0.8rem; background-color: #6c757d !important;">Extension Worker</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0 p-1 me-1 edit-user-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                title="Edit Details">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1 delete-user-btn" 
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                <?php echo $is_self ? 'disabled title="You cannot delete yourself"' : 'title="Delete Account"'; ?>>
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                    <span class="small text-muted">
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($filtered_users); ?> total filtered accounts)
                    </span>
                    <nav aria-label="User navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <!-- Numbered Pages -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo ($page === $i) ? 'bg-primary border-primary text-white' : ''; ?>" style="<?php echo ($page === $i) ? 'background-color: var(--primary-color) !important; border-color: var(--primary-color) !important;' : ''; ?>" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <!-- Next Page -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
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

<!-- ========================================== -->
<!-- MODAL: ADD USER                            -->
<!-- ========================================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: var(--radius-subtle);">
            <div class="modal-header border-bottom px-4">
                <h5 class="modal-title fw-bold" id="addUserModalLabel"><i class="bi bi-person-plus-fill me-2 text-primary" style="color: var(--primary-color) !important;"></i>Create System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/process_users.php" method="POST" id="addUserForm" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="add_username" class="form-label fw-semibold small text-muted">Username</label>
                        <input type="text" class="form-control" name="username" id="add_username" required placeholder="e.g. officer_john" minlength="3" maxlength="20" pattern="^[a-zA-Z0-9_]+$">
                        <div class="form-text small text-muted">Letters, numbers, and underscores only. Length: 3-20 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label fw-semibold small text-muted">Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control" name="password" id="add_password" required placeholder="Enter password (min 4 characters)" minlength="4" style="padding-right: 40px;">
                            <button type="button" class="btn btn-link toggle-password-btn" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">Password must contain at least 4 characters.</div>
                    </div>
                    <div class="mb-0">
                        <label for="add_role" class="form-label fw-semibold small text-muted">Access Control Role</label>
                        <select class="form-select" name="role" id="add_role" required>
                            <option value="" disabled selected>Select system permission...</option>
                            <option value="System Admin">System Admin (Full Settings Control)</option>
                            <option value="DA Officer">DA Officer (Data & AI Forecasting)</option>
                            <option value="Extension Worker">Extension Worker (Data Preview Only)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT USER                            -->
<!-- ========================================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: var(--radius-subtle);">
            <div class="modal-header border-bottom px-4">
                <h5 class="modal-title fw-bold" id="editUserModalLabel"><i class="bi bi-pencil-square me-2 text-primary" style="color: var(--primary-color) !important;"></i>Edit System Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/process_users.php" method="POST" id="editUserForm" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label fw-semibold small text-muted">Username</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required placeholder="e.g. officer_john" minlength="3" maxlength="20" pattern="^[a-zA-Z0-9_]+$">
                        <div class="form-text small text-muted">Letters, numbers, and underscores only. Length: 3-20 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label fw-semibold small text-muted">New Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control" name="password" id="edit_password" placeholder="Leave blank to keep existing password" minlength="4" style="padding-right: 40px;">
                            <button type="button" class="btn btn-link toggle-password-btn" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">Only fill this in if you want to change the user's password. Min 4 characters.</div>
                    </div>
                    <div class="mb-0">
                        <label for="edit_role" class="form-label fw-semibold small text-muted">Access Control Role</label>
                        <select class="form-select" name="role" id="edit_role" required>
                            <option value="System Admin">System Admin (Full Settings Control)</option>
                            <option value="DA Officer">DA Officer (Data & AI Forecasting)</option>
                            <option value="Extension Worker">Extension Worker (Data Preview Only)</option>
                        </select>
                        <div class="form-text small text-warning" id="self-role-warning" style="display: none;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> You cannot change your own admin role to prevent locking yourself out.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Populate Edit Modal Details
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = parseInt(button.getAttribute('data-id'));
            const username = button.getAttribute('data-username');
            const role = button.getAttribute('data-role');
            
            const currentUserId = <?php echo intval($_SESSION['user_id']); ?>;
            
            // Populating inputs
            editModal.querySelector('#edit_id').value = id;
            editModal.querySelector('#edit_username').value = username;
            editModal.querySelector('#edit_password').value = '';
            
            const roleSelect = editModal.querySelector('#edit_role');
            roleSelect.value = role;
            
            const warningText = editModal.querySelector('#self-role-warning');
            
            if (id === currentUserId) {
                // Prevent role alteration for self
                roleSelect.disabled = true;
                warningText.style.display = 'block';
                
                // Add a hidden input to submit the System Admin role since select is disabled
                let hiddenRole = editModal.querySelector('#hidden_role_input');
                if (!hiddenRole) {
                    hiddenRole = document.createElement('input');
                    hiddenRole.type = 'hidden';
                    hiddenRole.name = 'role';
                    hiddenRole.id = 'hidden_role_input';
                    hiddenRole.value = 'System Admin';
                    editModal.querySelector('form').appendChild(hiddenRole);
                }
            } else {
                roleSelect.disabled = false;
                warningText.style.display = 'none';
                
                const hiddenRole = editModal.querySelector('#hidden_role_input');
                if (hiddenRole) {
                    hiddenRole.remove();
                }
            }
        });
    }

    // 2. Delete User Action (AJAX with SweetAlert2 Confirmation)
    const deleteButtons = document.querySelectorAll('.delete-user-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

            Swal.fire({
                title: 'Delete System Account?',
                html: `Are you sure you want to delete the user account <strong>${username}</strong>? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash-fill me-1"></i>Yes, Delete Account',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX post
                    fetch('actions/process_users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'action': 'delete',
                            'id': id,
                            'csrf_token': csrfToken
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Account Deleted',
                                text: data.message,
                                confirmButtonColor: '#1b5e20'
                            }).then(() => {
                                // Delete row from UI list
                                const row = document.getElementById(`user-row-${id}`);
                                if (row) {
                                    row.remove();
                                    // Reload if no records left to update statistics and tables
                                    setTimeout(() => location.reload(), 200);
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Deletions Failed',
                                text: data.message,
                                confirmButtonColor: '#1b5e20'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('Delete request error: ', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Network Error',
                            text: 'Failed to process account deletion request. Please check connections.',
                            confirmButtonColor: '#1b5e20'
                        });
                    });
                }
            });
        });
    });

    // 3. Form Validation logic
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // 4. Toggle Password Visibility
    document.querySelectorAll('.toggle-password-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const container = btn.closest('.position-relative');
            if (!container) return;
            const input = container.querySelector('input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        });
    });
});
</script>

<!-- SweetAlert2 Trigger Alerts (from session redirects) -->
<?php if (isset($_SESSION['user_status'])): ?>
<script>
Swal.fire({
    icon: '<?php echo $_SESSION['user_status']['icon']; ?>',
    title: '<?php echo htmlspecialchars($_SESSION['user_status']['title']); ?>',
    text: '<?php echo htmlspecialchars($_SESSION['user_status']['text']); ?>',
    confirmButtonColor: '#1b5e20'
});
</script>
<?php unset($_SESSION['user_status']); endif; ?>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
