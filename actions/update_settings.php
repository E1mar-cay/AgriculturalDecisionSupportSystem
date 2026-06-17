<?php
/**
 * Update AI Engine Parameters Settings
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// 1. Verify User Role (Must be System Admin)
if ($_SESSION['role'] !== 'System Admin') {
    $_SESSION['settings_status'] = [
        'icon' => 'error',
        'title' => 'Access Denied',
        'text' => 'You do not have permission to modify settings.'
    ];
    header("Location: ../dashboard.php");
    exit();
}

// 2. Verify request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../settings.php");
    exit();
}

// 3. Verify CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['settings_status'] = [
        'icon' => 'error',
        'title' => 'Security Error',
        'text' => 'CSRF token validation failed. Please refresh the page.'
    ];
    header("Location: ../settings.php");
    exit();
}

// 4. Extract and Validate inputs
$support_raw = $_POST['min_support'] ?? '';
$confidence_raw = $_POST['min_confidence'] ?? '';

if ($support_raw === '' || $confidence_raw === '') {
    $_SESSION['settings_status'] = [
        'icon' => 'error',
        'title' => 'Validation Error',
        'text' => 'All parameters are required.'
    ];
    header("Location: ../settings.php");
    exit();
}

$min_support = floatval($support_raw);
$min_confidence = floatval($confidence_raw);

// Validate continuous bounds
if ($min_support < 0.01 || $min_support > 0.99 || $min_confidence < 0.01 || $min_confidence > 1.00) {
    $_SESSION['settings_status'] = [
        'icon' => 'error',
        'title' => 'Invalid Parameters',
        'text' => 'Selected thresholds are out of bounds. Support: 0.01 - 0.99. Confidence: 0.01 - 1.00.'
    ];
    header("Location: ../settings.php");
    exit();
}

try {
    // 5. Update settings in database
    $stmt = $pdo->prepare("UPDATE tbl_system_settings SET setting_value = :value WHERE setting_name = :name");
    $stmt->execute(['value' => strval($min_support), 'name' => 'min_support']);
    $stmt->execute(['value' => strval($min_confidence), 'name' => 'min_confidence']);
    
    // 6. Log settings update
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => "Updated AI parameters: Support = " . ($min_support * 100) . "%, Confidence = " . ($min_confidence * 100) . "%."
    ]);

    $_SESSION['settings_status'] = [
        'icon' => 'success',
        'title' => 'Parameters Saved',
        'text' => 'AI parameters updated successfully. Please run "Run AI Analysis" in Forecast Rules to regenerate rules.'
    ];
} catch (\PDOException $e) {
    error_log("Database settings update failed: " . $e->getMessage());
    $_SESSION['settings_status'] = [
        'icon' => 'error',
        'title' => 'Database Error',
        'text' => 'Failed to save parameters due to database error.'
    ];
}

header("Location: ../settings.php");
exit();
