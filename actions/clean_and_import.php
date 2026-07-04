<?php
/**
 * Clean and Import CSV Process
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Check if request is POST and file was uploaded
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header("Location: ../data_management.php");
    exit();
}

$file = $_FILES['csv_file'];

// Verify upload error code
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_status'] = [
        'icon' => 'error',
        'title' => 'Upload Failed',
        'text' => 'An upload error occurred (Code: ' . $file['error'] . ').'
    ];
    header("Location: ../data_management.php");
    exit();
}

// Verify file size (limit to 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    $_SESSION['upload_status'] = [
        'icon' => 'error',
        'title' => 'File Too Large',
        'text' => 'The uploaded file exceeds the 5MB size limit.'
    ];
    header("Location: ../data_management.php");
    exit();
}

// Verify extension
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($file_ext !== 'csv') {
    $_SESSION['upload_status'] = [
        'icon' => 'error',
        'title' => 'Invalid File Type',
        'text' => 'Please upload a valid CSV file (.csv).'
    ];
    header("Location: ../data_management.php");
    exit();
}

// Setup upload path inside uploads directory
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$temp_filename = uniqid('rsbsa_', true) . '.csv';
$dest_path = $upload_dir . $temp_filename;

// Move the file into uploads directory temporarily
if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    $_SESSION['upload_status'] = [
        'icon' => 'error',
        'title' => 'Server Error',
        'text' => 'Failed to save the uploaded file to the server.'
    ];
    header("Location: ../data_management.php");
    exit();
}

$inserted_count = 0;
$skipped_count = 0;
$row_num = 0;

// Default fallback indices for column mapping
$header_map = [
    'barangay' => 0,
    'crop_type' => 1,
    'farm_size' => 2,
    'season' => 3,
    'intervention' => 4,
    'fertilizer_type' => 5,
    'application_type' => 6
];

if (($handle = fopen($dest_path, 'r')) !== FALSE) {
    // Prepare the database statement once for efficiency
    $stmt = $pdo->prepare("
        INSERT INTO tbl_rsbsa_data (barangay, crop_type, farm_size, season, intervention_received, fertilizer_type, application_type) 
        VALUES (:barangay, :crop_type, :farm_size, :season, :intervention_received, :fertilizer_type, :application_type)
    ");

    while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row_num++;

        // Detect and skip header row
        if ($row_num === 1) {
            $is_header = false;
            foreach ($row as $val) {
                $cleaned = strtolower(trim($val));
                if (in_array($cleaned, ['barangay', 'crop_type', 'crop', 'farm_size', 'farm_size_ha', 'season', 'intervention', 'reference_no'])) {
                    $is_header = true;
                    break;
                }
            }

            if ($is_header) {
                // Map columns dynamically based on header names
                foreach ($row as $index => $val) {
                    $col = strtolower(trim($val));
                    if (str_contains($col, 'barangay')) {
                        $header_map['barangay'] = $index;
                    } elseif (str_contains($col, 'crop')) {
                        $header_map['crop_type'] = $index;
                    } elseif (str_contains($col, 'farm_size') || str_contains($col, 'size') || str_contains($col, 'area')) {
                        $header_map['farm_size'] = $index;
                    } elseif (str_contains($col, 'season')) {
                        $header_map['season'] = $index;
                    } elseif (str_contains($col, 'intervention')) {
                        $header_map['intervention'] = $index;
                    } elseif (str_contains($col, 'fertilizer')) {
                        $header_map['fertilizer_type'] = $index;
                    } elseif (str_contains($col, 'application')) {
                        $header_map['application_type'] = $index;
                    }
                }
                continue;
            }
        }

        // Validate column count (needs to contain all mapped header columns)
        $max_index = max(array_values($header_map));
        if (count($row) <= $max_index) {
            $skipped_count++;
            continue;
        }

        // Extract and trim fields using mapped headers
        $barangay = trim($row[$header_map['barangay']] ?? '');
        $crop_type = trim($row[$header_map['crop_type']] ?? '');
        $farm_size_raw = trim($row[$header_map['farm_size']] ?? '');
        $season_raw = trim($row[$header_map['season']] ?? '');
        $intervention = trim($row[$header_map['intervention']] ?? '');
        $fertilizer_type = trim($row[$header_map['fertilizer_type']] ?? '');
        $application_type = trim($row[$header_map['application_type']] ?? '');

        // Validation: Critical data check (none can be empty strings)
        if ($barangay === '' || $crop_type === '' || $farm_size_raw === '' || $season_raw === '' || $intervention === '' || $fertilizer_type === '' || $application_type === '') {
            $skipped_count++;
            continue;
        }

        // Validation: Farm size must be numeric and positive
        if (!is_numeric($farm_size_raw)) {
            $skipped_count++;
            continue;
        }
        $farm_size_val = floatval($farm_size_raw);
        if ($farm_size_val <= 0) {
            $skipped_count++;
            continue;
        }
        $farm_size = $farm_size_val * 10000; // Convert Hectares (ha) to Square Meters (sq.m)

        // Validation & Normalization: Season column mapping to ENUM values
        $season_norm = strtolower($season_raw);
        if (str_contains($season_norm, 'wet')) {
            $season = 'Wet Season';
        } elseif (str_contains($season_norm, 'dry')) {
            $season = 'Dry Season';
        } else {
            // Unrecognized season format, skip
            $skipped_count++;
            continue;
        }

        // Sanitization: Remove duplicate internal whitespaces and convert to Title Case
        $barangay = ucwords(strtolower(preg_replace('/\s+/', ' ', $barangay)));
        $crop_type = ucwords(strtolower(preg_replace('/\s+/', ' ', $crop_type)));
        $intervention = ucwords(strtolower(preg_replace('/\s+/', ' ', $intervention)));
        $fertilizer_type = ucwords(strtolower(preg_replace('/\s+/', ' ', $fertilizer_type)));
        $application_type = ucwords(strtolower(preg_replace('/\s+/', ' ', $application_type)));

        // Validation: Crop Type scope check (strictly Rice or Corn)
        if ($crop_type !== 'Rice' && $crop_type !== 'Corn') {
            $skipped_count++;
            continue;
        }

        // Insert into database
        try {
            $stmt->execute([
                'barangay' => $barangay,
                'crop_type' => $crop_type,
                'farm_size' => $farm_size,
                'season' => $season,
                'intervention_received' => $intervention,
                'fertilizer_type' => $fertilizer_type,
                'application_type' => $application_type
            ]);
            $inserted_count++;
        } catch (\PDOException $e) {
            error_log("Database row insert error during CSV upload: " . $e->getMessage());
            $skipped_count++;
        }
    }
    
    fclose($handle);
    // Delete temp CSV file to ensure uploads folder remains empty and clean
    unlink($dest_path);
} else {
    $_SESSION['upload_status'] = [
        'icon' => 'error',
        'title' => 'File Read Error',
        'text' => 'Could not read the saved CSV file.'
    ];
    header("Location: ../data_management.php");
    exit();
}

// Log execution in system logs
try {
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => "Imported RSBSA CSV: {$inserted_count} records inserted, {$skipped_count} skipped."
    ]);
} catch (\PDOException $e) {
    error_log("Audit logging failed during CSV upload: " . $e->getMessage());
}

// Set success/error feedback variables
$_SESSION['upload_status'] = [
    'icon' => $inserted_count > 0 ? 'success' : 'info',
    'title' => $inserted_count > 0 ? 'Data Upload Completed' : 'No Records Uploaded',
    'text' => "Successfully imported {$inserted_count} records. Skipped {$skipped_count} invalid records."
];

header("Location: ../data_management.php");
exit();
