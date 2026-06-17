<?php
/**
 * Login Interface
 * Smart Agricultural Decision Support System
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Redirect to dashboard if session is already active
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Generate secure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check and consume login errors
$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Agricultural Decision Support System</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/custom_style.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <!-- Branding Section (Visible on large screens) -->
    <div class="login-branding">
        <!-- Animated visual circles in background -->
        <div class="branding-bg-circles">
            <div class="branding-circle-1"></div>
            <div class="branding-circle-2"></div>
        </div>
        
        <div class="login-branding-content">
            <div class="branding-logo">
                <i class="bi bi-tree-fill"></i>
            </div>
            <h1 class="branding-title">Smart Agricultural<br>Decision Support</h1>
            <p class="branding-desc">Harnessing Association Rule Mining and predictive data modeling to empower farmers, DA officers, and extension workers with crop insights and resource allocation guidelines.</p>
            
            <ul class="branding-features">
                <li>
                    <i class="bi bi-diagram-3-fill"></i>
                    <span>AI-Driven Association Rules (Apriori Engine)</span>
                </li>
                <li>
                    <i class="bi bi-pie-chart-fill"></i>
                    <span>Visual Crop Diversity & Land Metrics</span>
                </li>
                <li>
                    <i class="bi bi-bar-chart-steps"></i>
                    <span>Dynamic Resource Allocations & Planning</span>
                </li>
                <li>
                    <i class="bi bi-file-earmark-lock-fill"></i>
                    <span>System Administration & Audit Activity Logs</span>
                </li>
            </ul>
        </div>
        
        <div class="branding-footer">
            <span>Smart Agricultural Decision Support System &copy; <?php echo date('Y'); ?></span>
        </div>
    </div>

    <!-- Login Form Section -->
    <div class="login-form-area">
        <div class="login-form-container">
            <div class="login-form-header">
                <div class="d-flex align-items-center mb-2 d-lg-none">
                    <i class="bi bi-tree-fill text-success fs-3 me-2" style="color: var(--primary-color) !important;"></i>
                    <span class="fw-bold fs-4 text-dark uppercase tracking-wide">AGRICULTURE DSS</span>
                </div>
                <h2 class="login-form-title">Welcome Back</h2>
                <p class="login-form-subtitle">Please sign in to access the system.</p>
            </div>

            <?php if ($login_error): ?>
                <div class="alert alert-danger py-2.5 px-3 mb-4 d-flex align-items-center" role="alert" style="font-size: 0.88rem; border-radius: var(--radius-subtle);">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <span><?php echo htmlspecialchars($login_error); ?></span>
                </div>
            <?php endif; ?>

            <form action="actions/login_process.php" method="POST">
                <!-- Hidden CSRF token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="login-input-group">
                    <label for="username">Username</label>
                    <div class="login-input-wrapper">
                        <input type="text" class="form-control text-dark" id="username" name="username" required autocomplete="username" autofocus placeholder="Enter your username">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>

                <div class="login-input-group mb-4">
                    <label for="password">Password</label>
                    <div class="login-input-wrapper">
                        <input type="password" class="form-control text-dark" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password" style="padding-right: 42px;">
                        <i class="bi bi-lock-fill"></i>
                        <button type="button" class="btn btn-link toggle-password-btn" aria-label="Toggle password visibility">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100 btn-primary">
                    <span>Access System</span>
                    <i class="bi bi-box-arrow-in-right"></i>
                </button>
            </form>

            <div class="text-center mt-5 text-muted small d-lg-none">
                <span>Department of Agriculture &copy; <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.querySelectorAll('.toggle-password-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const wrapper = btn.closest('.login-input-wrapper');
        if (!wrapper) return;
        const input = wrapper.querySelector('input');
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
</script>
</body>
</html>
