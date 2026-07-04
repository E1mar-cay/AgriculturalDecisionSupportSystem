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
    $command = '"' . $python_exec . '" "' . $python_script . '" ' . escapeshellarg($support) . ' ' . escapeshellarg($confidence);
    
    $descriptorspec = array(
       0 => array("pipe", "r"),  // stdin
       1 => array("pipe", "w"),  // stdout
       2 => array("pipe", "w")   // stderr
    );
    
    $process = proc_open($command, $descriptorspec, $pipes);
    
    $stdout = '';
    $stderr = '';
    
    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        $return_value = proc_close($process);
    } else {
        $return_value = -1;
    }
    
    // Parse output
    $data = json_decode($stdout, true);
    
    // Check if script ran successfully
    if ($return_value === 0 && $data !== null && isset($data['rules'])) {
        // Log to audit trail
        $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => "Triggered Apriori & FP-Growth algorithms from UI using Support = " . ($support * 100) . "%, Confidence = " . ($confidence * 100) . "%. Apriori: " . number_format($data['apriori_time_ms'], 2) . "ms, FP-Growth: " . number_format($data['fpgrowth_time_ms'], 2) . "ms."
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Association rules successfully recalculated and updated using settings parameters.',
            'rules' => $data['rules'],
            'apriori_time_ms' => $data['apriori_time_ms'],
            'fpgrowth_time_ms' => $data['fpgrowth_time_ms']
        ]);
    } else {
        error_log("Apriori/FP-Growth triggering failed. Exit Code: " . $return_value . ". Stderr: " . $stderr . ". Stdout: " . $stdout);
        
        // Return clear error detail
        $err_msg = 'Apriori/FP-Growth ran but did not generate any rules. Check if the dataset is too small or if system settings thresholds are set too high.';
        if (str_contains($stderr, 'Database Connection Error') || str_contains($stdout, 'Database Connection Error')) {
            $err_msg = 'Failed to connect to database in Python engine.';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $err_msg,
            'stderr' => $stderr,
            'stdout' => $stdout
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
