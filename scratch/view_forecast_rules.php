<?php
require_once __DIR__ . '/../includes/db_connect.php';
$rules = $pdo->query("SELECT * FROM tbl_forecast_rules LIMIT 10")->fetchAll();
foreach ($rules as $r) {
    echo "ID: {$r['id']} | Antecedents: {$r['antecedents']} | Consequents: {$r['consequents']}\n";
}
