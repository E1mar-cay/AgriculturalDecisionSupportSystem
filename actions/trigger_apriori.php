<?php
/**
 * Trigger Apriori Script (Bridge PHP to Python) - JSON Response
 * Smart Agricultural Decision Support System
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please log in.'
    ]);
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

try {
    // 1. Fetch dynamic support and confidence from settings
    $support = 0.10;
    $confidence = 0.50;
    try {
        $settingsStmt = $pdo->query("SELECT setting_name, setting_value FROM tbl_system_settings");
        $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (isset($settings['min_support'])) {
            $support = floatval($settings['min_support']);
        }
        if (isset($settings['min_confidence'])) {
            $confidence = floatval($settings['min_confidence']);
        }
    } catch (\PDOException $e) {
        error_log("Failed to load settings in trigger_apriori: " . $e->getMessage());
    }

    // Define absolute paths for Python interpreter and Apriori script
    $python_exec = 'c:\\xampp\\htdocs\\agricultural_dss\\.venv\\Scripts\\python.exe';
    $python_script = 'c:\\xampp\\htdocs\\agricultural_dss\\python_engine\\apriori_engine.py';

    // Construct command, escaping arguments properly
    $command = '"' . $python_exec . '" "' . $python_script . '" ' . escapeshellarg($support) . ' ' . escapeshellarg($confidence) . ' 2>&1';
    
    // Execute command and capture output
    $output = shell_exec($command);
    
    // Check if script ran successfully
    if ($output !== null && str_contains($output, 'Success!')) {
        // Log to audit trail
        $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => "Triggered Apriori algorithm from UI using Support = " . ($support * 100) . "%, Confidence = " . ($confidence * 100) . "%."
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Association rules successfully recalculated and updated using settings parameters.'
        ]);
    } else {
        error_log("Apriori triggering failed. Output: " . ($output ?? 'No output'));
        
        // Return clear error detail
        $err_msg = 'Apriori ran but did not generate any rules. Check if the dataset is too small or if system settings thresholds are set too high.';
        if ($output && str_contains($output, 'Database Connection Error')) {
            $err_msg = 'Failed to connect to database in Python engine.';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $err_msg
        ]);
    }
} catch (\Exception $e) {
    error_log("Trigger Apriori Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An execution error occurred on the server: ' . $e->getMessage()
    ]);
}
exit();
