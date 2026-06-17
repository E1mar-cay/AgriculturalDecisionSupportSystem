<?php
require_once __DIR__ . '/../includes/db_connect.php';
$records = $pdo->query("SELECT COUNT(*) FROM tbl_rsbsa_data")->fetchColumn();
$rules = $pdo->query("SELECT COUNT(*) FROM tbl_forecast_rules")->fetchColumn();
echo "Records in tbl_rsbsa_data: $records\n";
echo "Rules in tbl_forecast_rules: $rules\n";
