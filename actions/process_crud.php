<?php
/**
 * Process CRUD Operations for RSBSA Records
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Set response header for AJAX delete
if (isset($_POST['action']) && ($_POST['action'] === 'delete' || $_POST['action'] === 'batch_delete')) {
    header('Content-Type: application/json');
}

// 1. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'delete' || $_POST['action'] === 'batch_delete')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    } else {
        header("Location: ../data_management.php");
    }
    exit();
}

// 2. Verify CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    if (isset($_POST['action']) && ($_POST['action'] === 'delete' || $_POST['action'] === 'batch_delete')) {
        echo json_encode(['success' => false, 'message' => 'Security Error: CSRF token validation failed.']);
    } else {
        $_SESSION['upload_status'] = [
            'icon' => 'error',
            'title' => 'Security Error',
            'text' => 'CSRF token validation failed. Please refresh the page.'
        ];
        header("Location: ../data_management.php");
    }
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        // Extract, trim, normalize Title Case
        $barangay = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['barangay'] ?? ''))));
        $crop_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['crop_type'] ?? ''))));
        $farm_size_raw = trim($_POST['farm_size'] ?? '');
        $season = trim($_POST['season'] ?? '');
        $intervention = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['intervention_received'] ?? ''))));
        $fertilizer_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['fertilizer_type'] ?? ''))));
        $application_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['application_type'] ?? ''))));

        // Validation: Ensure no fields are empty
        if ($barangay === '' || $crop_type === '' || $farm_size_raw === '' || $season === '' || $intervention === '' || $fertilizer_type === '' || $application_type === '') {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'All fields are required.'
            ];
            header("Location: ../data_management.php");
            exit();
        }

        // Validation: Farm size positive float
        if (!is_numeric($farm_size_raw) || floatval($farm_size_raw) <= 0) {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Farm size must be a positive numeric value.'
            ];
            header("Location: ../data_management.php");
            exit();
        }
        $farm_size = floatval($farm_size_raw);

        // Validation: Season check
        if ($season !== 'Wet Season' && $season !== 'Dry Season') {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid season selected.'
            ];
            header("Location: ../data_management.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_rsbsa_data (barangay, crop_type, farm_size, season, intervention_received, fertilizer_type, application_type) 
                VALUES (:barangay, :crop_type, :farm_size, :season, :intervention_received, :fertilizer_type, :application_type)
            ");
            $stmt->execute([
                'barangay' => $barangay,
                'crop_type' => $crop_type,
                'farm_size' => $farm_size,
                'season' => $season,
                'intervention_received' => $intervention,
                'fertilizer_type' => $fertilizer_type,
                'application_type' => $application_type
            ]);
            $new_id = $pdo->lastInsertId();

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Created RSBSA record ID {$new_id} - Barangay: {$barangay}, Crop: {$crop_type}, Size: {$farm_size} sq.m, Fertilizer: {$fertilizer_type}, Application: {$application_type}."
            ]);

            $_SESSION['upload_status'] = [
                'icon' => 'success',
                'title' => 'Record Added',
                'text' => "Successfully created record ID {$new_id} in {$barangay}."
            ];
        } catch (\PDOException $e) {
            error_log("Database error in CRUD Create: " . $e->getMessage());
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Failed to create record due to a database error.'
            ];
        }
        header("Location: ../data_management.php");
        exit();

    case 'update':
        $id = intval($_POST['id'] ?? 0);
        $barangay = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['barangay'] ?? ''))));
        $crop_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['crop_type'] ?? ''))));
        $farm_size_raw = trim($_POST['farm_size'] ?? '');
        $season = trim($_POST['season'] ?? '');
        $intervention = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['intervention_received'] ?? ''))));
        $fertilizer_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['fertilizer_type'] ?? ''))));
        $application_type = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['application_type'] ?? ''))));

        if ($id <= 0) {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid record ID.'
            ];
            header("Location: ../data_management.php");
            exit();
        }

        // Validation: Empty check
        if ($barangay === '' || $crop_type === '' || $farm_size_raw === '' || $season === '' || $intervention === '' || $fertilizer_type === '' || $application_type === '') {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'All fields are required.'
            ];
            header("Location: ../data_management.php");
            exit();
        }

        // Validation: Farm size
        if (!is_numeric($farm_size_raw) || floatval($farm_size_raw) <= 0) {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Farm size must be a positive numeric value.'
            ];
            header("Location: ../data_management.php");
            exit();
        }
        $farm_size = floatval($farm_size_raw);

        // Validation: Season
        if ($season !== 'Wet Season' && $season !== 'Dry Season') {
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid season selected.'
            ];
            header("Location: ../data_management.php");
            exit();
        }

        try {
            // Fetch old record details for detailed audit log
            $oldStmt = $pdo->prepare("SELECT * FROM tbl_rsbsa_data WHERE id = :id");
            $oldStmt->execute(['id' => $id]);
            $old = $oldStmt->fetch();

            if (!$old) {
                $_SESSION['upload_status'] = [
                    'icon' => 'error',
                    'title' => 'Not Found',
                    'text' => 'Record does not exist.'
                ];
                header("Location: ../data_management.php");
                exit();
            }

            // Perform Update
            $updateStmt = $pdo->prepare("
                UPDATE tbl_rsbsa_data 
                SET barangay = :barangay, crop_type = :crop_type, farm_size = :farm_size, season = :season, intervention_received = :intervention, fertilizer_type = :fertilizer_type, application_type = :application_type 
                WHERE id = :id
            ");
            $updateStmt->execute([
                'barangay' => $barangay,
                'crop_type' => $crop_type,
                'farm_size' => $farm_size,
                'season' => $season,
                'intervention' => $intervention,
                'fertilizer_type' => $fertilizer_type,
                'application_type' => $application_type,
                'id' => $id
            ]);

            // Construct list of changes
            $changes = [];
            if ($old['barangay'] !== $barangay) $changes[] = "Barangay: {$old['barangay']} -> {$barangay}";
            if ($old['crop_type'] !== $crop_type) $changes[] = "Crop: {$old['crop_type']} -> {$crop_type}";
            if (floatval($old['farm_size']) !== floatval($farm_size)) $changes[] = "Size: {$old['farm_size']} -> {$farm_size}";
            if ($old['season'] !== $season) $changes[] = "Season: {$old['season']} -> {$season}";
            if ($old['intervention_received'] !== $intervention) $changes[] = "Intervention: {$old['intervention_received']} -> {$intervention}";
            if (($old['fertilizer_type'] ?? '') !== $fertilizer_type) $changes[] = "Fertilizer: " . ($old['fertilizer_type'] ?? 'None') . " -> {$fertilizer_type}";
            if (($old['application_type'] ?? '') !== $application_type) $changes[] = "Application: " . ($old['application_type'] ?? 'None') . " -> {$application_type}";

            $change_desc = empty($changes) ? "No fields changed" : implode(', ', $changes);

            // Audit log
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Updated RSBSA record ID {$id} - Changes: {$change_desc}."
            ]);

            $_SESSION['upload_status'] = [
                'icon' => 'success',
                'title' => 'Record Updated',
                'text' => "Successfully updated record ID {$id}."
            ];
        } catch (\PDOException $e) {
            error_log("Database error in CRUD Update: " . $e->getMessage());
            $_SESSION['upload_status'] = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Failed to update record.'
            ];
        }
        header("Location: ../data_management.php");
        exit();

    case 'delete':
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
            exit();
        }

        try {
            // Fetch details to log
            $oldStmt = $pdo->prepare("SELECT barangay, crop_type FROM tbl_rsbsa_data WHERE id = :id");
            $oldStmt->execute(['id' => $id]);
            $old = $oldStmt->fetch();

            if (!$old) {
                echo json_encode(['success' => false, 'message' => 'Record does not exist.']);
                exit();
            }

            // Perform Delete
            $deleteStmt = $pdo->prepare("DELETE FROM tbl_rsbsa_data WHERE id = :id");
            $deleteStmt->execute(['id' => $id]);

            // Audit log
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Deleted RSBSA record ID {$id} - Barangay: {$old['barangay']}, Crop: {$old['crop_type']}."
            ]);

            echo json_encode(['success' => true, 'message' => "Successfully deleted record ID {$id}."]);
        } catch (\PDOException $e) {
            error_log("Database error in CRUD Delete: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete record due to database error.']);
        }
        exit();

    case 'batch_delete':
        $ids_raw = $_POST['ids'] ?? [];
        if (!is_array($ids_raw)) {
            echo json_encode(['success' => false, 'message' => 'Invalid IDs provided.']);
            exit();
        }

        $ids = array_filter(array_map('intval', $ids_raw), function($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No records selected for deletion.']);
            exit();
        }

        try {
            // Build dynamic safe placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Fetch record details to construct a single clean audit log entry
            $fetchStmt = $pdo->prepare("SELECT id, barangay, crop_type FROM tbl_rsbsa_data WHERE id IN ($placeholders)");
            $fetchStmt->execute($ids);
            $deleted_records = $fetchStmt->fetchAll();

            if (empty($deleted_records)) {
                echo json_encode(['success' => false, 'message' => 'Selected records do not exist.']);
                exit();
            }

            // Perform Bulk Delete
            $deleteStmt = $pdo->prepare("DELETE FROM tbl_rsbsa_data WHERE id IN ($placeholders)");
            $deleteStmt->execute($ids);

            // Construct audit log description
            $log_details = [];
            foreach ($deleted_records as $r) {
                $log_details[] = "ID {$r['id']}: {$r['barangay']} ({$r['crop_type']})";
            }
            $count = count($deleted_records);
            $log_desc = "Batch deleted {$count} RSBSA records. Details: " . implode(', ', $log_details);

            // Audit log
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => $log_desc
            ]);

            echo json_encode(['success' => true, 'message' => "Successfully batch deleted {$count} records."]);
        } catch (\PDOException $e) {
            error_log("Database error in CRUD Batch Delete: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete records due to database error.']);
        }
        exit();

    default:
        if (isset($_POST['action']) && ($_POST['action'] === 'delete' || $_POST['action'] === 'batch_delete')) {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        } else {
            header("Location: ../data_management.php");
        }
        exit();
}
