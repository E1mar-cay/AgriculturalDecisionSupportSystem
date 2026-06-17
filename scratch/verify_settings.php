<?php
/**
 * Settings Updates Verification Script
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/db_connect.php';

echo "=== STARTING SETTINGS UPDATE VERIFICATION ===\n";

// 1. Back up current settings
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM tbl_system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $orig_support = $settings['min_support'] ?? '0.10';
    $orig_confidence = $settings['min_confidence'] ?? '0.50';
    echo "SUCCESS: Saved current backup (Support: {$orig_support}, Confidence: {$orig_confidence})\n";
} catch (\Exception $e) {
    echo "FAILED: Could not backup settings: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Perform test updates
$test_support = '0.15';
$test_confidence = '0.60';

try {
    $stmt = $pdo->prepare("UPDATE tbl_system_settings SET setting_value = :value WHERE setting_name = :name");
    $stmt->execute(['value' => $test_support, 'name' => 'min_support']);
    $stmt->execute(['value' => $test_confidence, 'name' => 'min_confidence']);
    
    // Log test action
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => 1,
        'action' => "VERIFY SETTINGS TEST - Updated AI parameters: Support = " . (floatval($test_support) * 100) . "%, Confidence = " . (floatval($test_confidence) * 100) . "%."
    ]);
    echo "SUCCESS: Updated settings to test thresholds and wrote audit log.\n";
} catch (\Exception $e) {
    echo "FAILED: Could not update settings in database: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verify database state
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM tbl_system_settings");
    $updated = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (abs(floatval($updated['min_support']) - floatval($test_support)) < 0.001 && 
        abs(floatval($updated['min_confidence']) - floatval($test_confidence)) < 0.001) {
        echo "SUCCESS: Database state matches updated test values.\n";
    } else {
        throw new Exception("Database settings values do not match updates.");
    }
} catch (\Exception $e) {
    echo "FAILED: Settings state verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verify system logs
try {
    $logStmt = $pdo->prepare("SELECT action FROM tbl_system_logs WHERE action LIKE 'VERIFY SETTINGS TEST%' LIMIT 1");
    $logStmt->execute();
    $log = $logStmt->fetchColumn();
    
    if ($log) {
        echo "SUCCESS: Found test audit log entry: {$log}\n";
    } else {
        throw new Exception("Audit log entry not found.");
    }
} catch (\Exception $e) {
    echo "FAILED: Logs verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Restore original parameters and clean logs
try {
    $stmt = $pdo->prepare("UPDATE tbl_system_settings SET setting_value = :value WHERE setting_name = :name");
    $stmt->execute(['value' => $orig_support, 'name' => 'min_support']);
    $stmt->execute(['value' => $orig_confidence, 'name' => 'min_confidence']);
    
    $pdo->exec("DELETE FROM tbl_system_logs WHERE action LIKE 'VERIFY SETTINGS TEST%'");
    echo "SUCCESS: Restored original database settings and deleted verification logs.\n";
} catch (\Exception $e) {
    echo "FAILED: Cleanup and restore failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== ALL SETTINGS UPDATE TESTS PASSED SUCCESSFULLY ===\n";
