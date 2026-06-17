<?php
require_once __DIR__ . '/../includes/db_connect.php';
$stmt = $pdo->prepare("UPDATE tbl_system_settings SET setting_value = :value WHERE setting_name = :name");
$stmt->execute(['value' => '0.10', 'name' => 'min_support']);
$stmt->execute(['value' => '0.50', 'name' => 'min_confidence']);
echo "SUCCESS: Reset settings to default values.\n";
