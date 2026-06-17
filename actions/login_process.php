<?php
/**
 * Login Process Action
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Restrict access to POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

// CSRF Token Validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['login_error'] = "Security validation failed. Please refresh and try again.";
    header("Location: ../index.php");
    exit();
}

// Gather inputs
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Username and password are required.";
    header("Location: ../index.php");
    exit();
}

try {
    // Retrieve user details
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM tbl_users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Write to audit logs
        $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
        $logStmt->execute([
            'user_id' => $user['id'],
            'action' => "Successful login."
        ]);

        // Cleanup CSRF token to prevent reuse, index.php will regenerate a new one if log out happens
        unset($_SESSION['csrf_token']);

        // Redirect to dashboard
        header("Location: ../dashboard.php");
        exit();
    } else {
        // Audit log failed attempt (no user ID linked)
        $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (NULL, :action)");
        $logStmt->execute([
            'action' => "Failed login attempt for username: " . substr(htmlspecialchars($username), 0, 50)
        ]);

        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: ../index.php");
        exit();
    }
} catch (\PDOException $e) {
    error_log("Login Process Error: " . $e->getMessage());
    $_SESSION['login_error'] = "A system error occurred. Please try again later.";
    header("Location: ../index.php");
    exit();
}
