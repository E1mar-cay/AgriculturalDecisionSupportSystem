<?php
/**
 * Database Connection Script
 * Smart Agricultural Decision Support System (Agri-DSS)
 */

$host = 'localhost';
$db   = 'db_agri_dss';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log error details for the developer, but show a clean error to the user
    error_log("Database connection failed: " . $e->getMessage());
    die("A database connection error occurred. Please contact the system administrator.");
}
