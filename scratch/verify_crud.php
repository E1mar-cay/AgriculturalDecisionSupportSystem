<?php
/**
 * Programmatic CRUD & Batch Delete Verification Script
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/db_connect.php';

echo "=== STARTING CRUD & BATCH DELETE VERIFICATION TEST ===\n";

// Ensure a test user exists or find one
$userStmt = $pdo->query("SELECT id, username FROM tbl_users LIMIT 1");
$user = $userStmt->fetch();
if (!$user) {
    echo "ERROR: No users found in database to log actions for.\n";
    exit(1);
}
$user_id = $user['id'];
echo "Testing with user: {$user['username']} (ID: {$user_id})\n";

// Clean up leftover test logs/data first
$pdo->exec("DELETE FROM tbl_system_logs WHERE action LIKE 'VERIFY TEST%'");
$pdo->exec("DELETE FROM tbl_rsbsa_data WHERE barangay LIKE 'Verify%'");

// 1. Verify Create
echo "\n1. Testing Create...\n";
$barangay = "Verify Barangay Alpha";
$crop_type = "Verify Rice Gold";
$farm_size = 3.75;
$season = "Wet Season";
$intervention = "Verify Fertilizer Bags A";

try {
    $stmt = $pdo->prepare("
        INSERT INTO tbl_rsbsa_data (barangay, crop_type, farm_size, season, intervention_received) 
        VALUES (:barangay, :crop_type, :farm_size, :season, :intervention)
    ");
    $stmt->execute([
        'barangay' => $barangay,
        'crop_type' => $crop_type,
        'farm_size' => $farm_size,
        'season' => $season,
        'intervention' => $intervention
    ]);
    $test_id = $pdo->lastInsertId();
    echo "SUCCESS: Created record with ID {$test_id}.\n";

    // Write audit log
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $user_id,
        'action' => "VERIFY TEST - Created record ID {$test_id}."
    ]);
    echo "SUCCESS: Created audit log entry for creation.\n";
} catch (\Exception $e) {
    echo "FAILED: Create record failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Verify Read / Update
echo "\n2. Testing Update...\n";
$new_crop_type = "Verify Rice Gold Super";
$new_farm_size = 4.25;

try {
    // Fetch old record
    $oldStmt = $pdo->prepare("SELECT * FROM tbl_rsbsa_data WHERE id = :id");
    $oldStmt->execute(['id' => $test_id]);
    $old = $oldStmt->fetch();
    if (!$old) {
        throw new Exception("Record not found after insertion.");
    }

    // Update
    $updateStmt = $pdo->prepare("
        UPDATE tbl_rsbsa_data 
        SET crop_type = :crop_type, farm_size = :farm_size 
        WHERE id = :id
    ");
    $updateStmt->execute([
        'crop_type' => $new_crop_type,
        'farm_size' => $new_farm_size,
        'id' => $test_id
    ]);

    // Check changes
    $changes = [];
    if ($old['crop_type'] !== $new_crop_type) $changes[] = "Crop: {$old['crop_type']} -> {$new_crop_type}";
    if (floatval($old['farm_size']) !== floatval($new_farm_size)) $changes[] = "Size: {$old['farm_size']} -> {$new_farm_size}";

    $change_desc = implode(', ', $changes);
    echo "Changes detected: {$change_desc}\n";

    // Write audit log
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $user_id,
        'action' => "VERIFY TEST - Updated record ID {$test_id} - Changes: {$change_desc}."
    ]);
    echo "SUCCESS: Updated record and logged changes.\n";
} catch (\Exception $e) {
    echo "FAILED: Update record failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verify Read again
echo "\n3. Verifying database state...\n";
try {
    $verifyStmt = $pdo->prepare("SELECT * FROM tbl_rsbsa_data WHERE id = :id");
    $verifyStmt->execute(['id' => $test_id]);
    $updated_record = $verifyStmt->fetch();

    if ($updated_record['crop_type'] === $new_crop_type && floatval($updated_record['farm_size']) === $new_farm_size) {
        echo "SUCCESS: Database has correct updated values.\n";
    } else {
        throw new Exception("Database values do not match expected updates.");
    }
} catch (\Exception $e) {
    echo "FAILED: Database state verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verify Delete
echo "\n4. Testing Delete...\n";
try {
    $deleteStmt = $pdo->prepare("DELETE FROM tbl_rsbsa_data WHERE id = :id");
    $deleteStmt->execute(['id' => $test_id]);

    // Log delete
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $user_id,
        'action' => "VERIFY TEST - Deleted record ID {$test_id}."
    ]);

    // Check database to ensure it's gone
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_rsbsa_data WHERE id = :id");
    $checkStmt->execute(['id' => $test_id]);
    if (intval($checkStmt->fetchColumn()) === 0) {
        echo "SUCCESS: Record is no longer present in database.\n";
    } else {
        throw new Exception("Record was not deleted.");
    }
} catch (\Exception $e) {
    echo "FAILED: Delete failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Verify Batch Delete
echo "\n5. Testing Batch Delete...\n";
try {
    // Insert 3 batch records
    $stmt = $pdo->prepare("
        INSERT INTO tbl_rsbsa_data (barangay, crop_type, farm_size, season, intervention_received) 
        VALUES 
        ('Verify Barangay B1', 'Rice B1', 1.0, 'Wet Season', 'Seeds B1'),
        ('Verify Barangay B2', 'Rice B2', 2.0, 'Wet Season', 'Seeds B2'),
        ('Verify Barangay B3', 'Rice B3', 3.0, 'Dry Season', 'Seeds B3')
    ");
    $stmt->execute();
    
    // Retrieve the 3 inserted IDs
    $fetchStmt = $pdo->query("SELECT id FROM tbl_rsbsa_data WHERE barangay LIKE 'Verify Barangay B%' ORDER BY id ASC");
    $batch_ids = $fetchStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($batch_ids) !== 3) {
        throw new Exception("Failed to insert 3 batch rows. Found: " . count($batch_ids));
    }
    echo "SUCCESS: Inserted 3 test records for batch deletion (IDs: " . implode(', ', $batch_ids) . ").\n";

    // Build dynamic placeholders
    $placeholders = implode(',', array_fill(0, count($batch_ids), '?'));

    // Fetch details for logging
    $infoStmt = $pdo->prepare("SELECT id, barangay, crop_type FROM tbl_rsbsa_data WHERE id IN ($placeholders)");
    $infoStmt->execute($batch_ids);
    $records_to_batch_delete = $infoStmt->fetchAll();

    // Perform Delete
    $delStmt = $pdo->prepare("DELETE FROM tbl_rsbsa_data WHERE id IN ($placeholders)");
    $delStmt->execute($batch_ids);
    echo "SUCCESS: Executed batch delete query.\n";

    // Write a single audit log
    $log_details = [];
    foreach ($records_to_batch_delete as $r) {
        $log_details[] = "ID {$r['id']}: {$r['barangay']} ({$r['crop_type']})";
    }
    $log_desc = "VERIFY TEST - Batch deleted " . count($records_to_batch_delete) . " RSBSA records. Details: " . implode(', ', $log_details);
    
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $user_id,
        'action' => $log_desc
    ]);
    echo "SUCCESS: Logged single summarizing audit log entry.\n";

    // Verify deletion in database
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_rsbsa_data WHERE id IN ($placeholders)");
    $checkStmt->execute($batch_ids);
    if (intval($checkStmt->fetchColumn()) === 0) {
        echo "SUCCESS: All batch records deleted from database.\n";
    } else {
        throw new Exception("Some batch records were not deleted.");
    }

} catch (\Exception $e) {
    echo "FAILED: Batch Delete failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 6. Verify System Logs
echo "\n6. Verifying system log entries...\n";
try {
    $logsStmt = $pdo->prepare("
        SELECT action, created_at 
        FROM tbl_system_logs 
        WHERE action LIKE 'VERIFY TEST%' 
        ORDER BY id ASC
    ");
    $logsStmt->execute();
    $log_rows = $logsStmt->fetchAll();

    if (count($log_rows) === 4) {
        echo "SUCCESS: Found all 4 verification audit logs:\n";
        foreach ($log_rows as $row) {
            echo " - [{$row['created_at']}] {$row['action']}\n";
        }
    } else {
        throw new Exception("Expected 4 verification logs, found " . count($log_rows));
    }

    // Clean up verification logs/data
    $pdo->exec("DELETE FROM tbl_system_logs WHERE action LIKE 'VERIFY TEST%'");
    $pdo->exec("DELETE FROM tbl_rsbsa_data WHERE barangay LIKE 'Verify%'");
    echo "SUCCESS: Cleaned up verification logs and tables.\n";

} catch (\Exception $e) {
    echo "FAILED: Log verification failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== ALL CRUD & BATCH DELETE TESTS PASSED SUCCESSFULLY ===\n";
