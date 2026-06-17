<?php
/**
 * Database Migration: Convert farm_size from Hectares to Square Meters
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/db_connect.php';

echo "=== RUNNING DB UNIT MIGRATION (HA -> SQM) ===\n";

try {
    // Only convert if they are likely in hectares (under 1000)
    $stmt = $pdo->prepare("UPDATE tbl_rsbsa_data SET farm_size = farm_size * 10000 WHERE farm_size < 1000");
    $stmt->execute();
    $updated_rows = $stmt->rowCount();
    
    echo "SUCCESS: Converted {$updated_rows} records from hectares to square meters (multiplied by 10,000).\n";
    
    // Log the migration
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    // Simulating system or first user log
    $logStmt->execute([
        'user_id' => 1, // System Admin
        'action' => "Executed database migration: converted {$updated_rows} farm size records to square meters (sq.m)."
    ]);
    
} catch (\PDOException $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== MIGRATION COMPLETE ===\n";
