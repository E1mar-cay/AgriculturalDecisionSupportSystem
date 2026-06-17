<?php
/**
 * Authentication Check Helper
 * Smart Agricultural Decision Support System
 */

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // Secure flag is only set if on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Ensure CSRF token is initialized for the session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Destroy potentially partial session data
    session_unset();
    session_destroy();
    
    // Start session again to set error message
    session_start();
    $_SESSION['login_error'] = "Session expired or access denied. Please log in.";
    header("Location: index.php");
    exit();
}
