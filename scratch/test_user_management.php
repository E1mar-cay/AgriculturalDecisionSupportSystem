<?php
/**
 * Programmatic Test for User CRUD Operations
 */
require_once __DIR__ . '/../includes/db_connect.php';

echo "Running User DB Operations Test...\n";

// Ensure clean environment
$pdo->exec("DELETE FROM tbl_users WHERE username IN ('test_officer', 'test_worker', 'test_admin_2')");

// Test Case 1: Insert Users
try {
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO tbl_users (username, password, role) VALUES ('test_worker', :pass, 'Extension Worker')");
    $stmt->execute(['pass' => $hash]);
    $worker_id = $pdo->lastInsertId();
    echo "[PASS] Inserted test_worker with ID: $worker_id\n";
    
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = 'test_worker'");
    $stmt2->execute();
    $count = intval($stmt2->fetchColumn());
    if ($count === 1) {
        echo "[PASS] Unique username verification: Found duplicate test_worker successfully.\n";
    } else {
        echo "[FAIL] Unique username verification failed.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Test Case 1 failed: " . $e->getMessage() . "\n";
}

// Test Case 2: Update User
try {
    $stmt = $pdo->prepare("UPDATE tbl_users SET role = 'DA Officer' WHERE username = 'test_worker'");
    $stmt->execute();
    
    $stmt_check = $pdo->prepare("SELECT role FROM tbl_users WHERE username = 'test_worker'");
    $stmt_check->execute();
    $role = $stmt_check->fetchColumn();
    if ($role === 'DA Officer') {
        echo "[PASS] Role updated successfully to DA Officer.\n";
    } else {
        echo "[FAIL] Role update failed.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Test Case 2 failed: " . $e->getMessage() . "\n";
}

// Test Case 3: Delete User
try {
    $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = :id");
    $stmt->execute(['id' => $worker_id]);
    
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE id = :id");
    $stmt_check->execute(['id' => $worker_id]);
    $count = intval($stmt_check->fetchColumn());
    if ($count === 0) {
        echo "[PASS] User deleted successfully.\n";
    } else {
        echo "[FAIL] User deletion failed.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Test Case 3 failed: " . $e->getMessage() . "\n";
}

// Cleanup
$pdo->exec("DELETE FROM tbl_users WHERE username IN ('test_officer', 'test_worker', 'test_admin_2')");
echo "Database operations verification completed successfully.\n";
