<?php
/**
 * Logout Action
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    try {
        // Log the logout action in audit trail
        $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => "User logged out."
        ]);
    } catch (\PDOException $e) {
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Unset all session parameters
$_SESSION = [];

// Expire the session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Complete destruction of session
session_destroy();

// Redirect to login
header("Location: ../index.php");
exit();
