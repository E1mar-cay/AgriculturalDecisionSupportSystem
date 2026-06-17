<?php
/**
 * Export Agricultural Resource Allocation Report to PDF
 * Smart Agricultural Decision Support System
 */

// 1. Session and Authentication Check
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['login_error'] = "Session expired or access denied. Please log in.";
    header("Location: ../index.php");
    exit();
}

// 2. Load dependencies and database connection
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Fetch the aggregated allocations data from the database
try {
    $stmt = $pdo->query("
        SELECT barangay, crop_type, season, 
               SUM(farm_size) as total_area, 
               COUNT(*) as farmer_count 
        FROM tbl_rsbsa_data 
        GROUP BY barangay, crop_type, season 
        ORDER BY barangay ASC, crop_type ASC, season ASC
    ");
    $allocations = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Database read error in export_allocation_pdf: " . $e->getMessage());
    $allocations = [];
}

// 3. Pre-calculate overall sums
$grand_total_area = 0;
$grand_total_seeds_kg = 0;
$grand_total_fertilizer_bags = 0;
$processed_data = [];

foreach ($allocations as $row) {
    $area = floatval($row['total_area']);
    $crop = strtolower(trim($row['crop_type']));
    
    // Seed rate: Rice = 0.004 kg/sq.m, Corn = 0.002 kg/sq.m, Others = 0.003 kg/sq.m
    $seed_rate = 0.003;
    if ($crop === 'rice') {
        $seed_rate = 0.004;
    } elseif ($crop === 'corn') {
        $seed_rate = 0.002;
    }
    
    $seeds = $area * $seed_rate;
    $fertilizer = $area * 0.0003;

    $grand_total_area += $area;
    $grand_total_seeds_kg += $seeds;
    $grand_total_fertilizer_bags += $fertilizer;

    $processed_data[] = [
        'barangay' => $row['barangay'],
        'crop_type' => $row['crop_type'],
        'season' => $row['season'],
        'area' => $area,
        'farmer_count' => intval($row['farmer_count']),
        'seeds' => $seeds,
        'fertilizer' => $fertilizer
    ];
}

// Create custom PDF Class
class AgriculturalReportPDF extends \FPDF {
    
    // Page header
    function Header() {
        // Republic of the Philippines Header
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 4, 'REPUBLIC OF THE PHILIPPINES', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, 'DEPARTMENT OF AGRICULTURE', 0, 1, 'C');
        $this->Cell(0, 4, 'MUNICIPALITY OF CABATUAN, ISABELA', 0, 1, 'C');
        $this->Ln(3);
        
        // Document Title
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, 'ESTIMATED AGRICULTURAL RESOURCE ALLOCATION REPORT', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'C');
        $this->Ln(4);
        
        // Draw double line separator
        $y = $this->GetY();
        $this->Line(18.5, $y, 278.5, $y);
        $this->Line(18.5, $y + 0.8, 278.5, $y + 0.8);
        $this->Ln(5);
    }
    
    // Page footer
    function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

// Instantiate PDF in Landscape mode, A4
$pdf = new AgriculturalReportPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(18.5, 15, 18.5);
$pdf->AddPage();

// Write Summary Block
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'REPORT SUMMARY METRICS', 0, 1, 'L');
$pdf->Ln(2);

// Summary Table/Cards in PDF (3 columns)
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(86, 6, 'Total Farm Land Area (sq.m)', 1, 0, 'C', true);
$pdf->Cell(86, 6, 'Total Seeds Required (kg)', 1, 0, 'C', true);
$pdf->Cell(88, 6, 'Total Fertilizer Required (bags)', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(86, 8, number_format($grand_total_area, 1) . ' sq.m', 1, 0, 'C');
$pdf->Cell(86, 8, number_format($grand_total_seeds_kg, 2) . ' kg', 1, 0, 'C');
$pdf->Cell(88, 8, number_format($grand_total_fertilizer_bags, 2) . ' bags', 1, 1, 'C');
$pdf->Ln(6);

// Main Table Headers
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'ESTIMATED RESOURCE ALLOCATIONS BY BARANGAY', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(27, 94, 32); // Dark Green primary theme
$pdf->SetTextColor(255, 255, 255); // White text

// Table Columns: Barangay (50), Crop Type (35), Season (30), Area (35), Farmers (25), Seeds (40), Fertilizer (45)
$pdf->Cell(50, 8, 'Barangay', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Crop Type', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Season', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Land Area (sq.m)', 1, 0, 'R', true);
$pdf->Cell(25, 8, 'Farmers', 1, 0, 'R', true);
$pdf->Cell(40, 8, 'Seed Allocation (kg)', 1, 0, 'R', true);
$pdf->Cell(45, 8, 'Fertilizer Allocation (bags)', 1, 1, 'R', true);

// Reset font colors for rows
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

$fill = false;
foreach ($processed_data as $row) {
    // Determine alternating row color
    if ($fill) {
        $pdf->SetFillColor(245, 245, 245);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell(50, 7, $row['barangay'], 1, 0, 'L', true);
    $pdf->Cell(35, 7, $row['crop_type'], 1, 0, 'L', true);
    $pdf->Cell(30, 7, $row['season'], 1, 0, 'L', true);
    $pdf->Cell(35, 7, number_format($row['area'], 1), 1, 0, 'R', true);
    $pdf->Cell(25, 7, number_format($row['farmer_count']), 1, 0, 'R', true);
    
    // Highlight calculation cells slightly with very light green tint
    $pdf->SetFillColor($fill ? 240 : 253, $fill ? 248 : 255, $fill ? 240 : 253);
    $pdf->Cell(40, 7, number_format($row['seeds'], 1), 1, 0, 'R', true);
    $pdf->Cell(45, 7, number_format($row['fertilizer'], 1), 1, 1, 'R', true);
    
    $fill = !$fill;
}

$pdf->Ln(8);

// Add signature area
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(130, 5, '', 0, 0);
$pdf->Cell(130, 5, 'Prepared By:', 0, 1, 'L');
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 5, '', 0, 0);
$pdf->Cell(130, 5, strtoupper($_SESSION['username']), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(130, 5, '', 0, 0);
$pdf->Cell(130, 5, $_SESSION['role'] . ', Department of Agriculture', 0, 1, 'L');

// Audit Log entry
try {
    $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
    $logStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => "Exported Agricultural Resource Allocation Report (PDF)."
    ]);
} catch (\PDOException $e) {
    error_log("Failed to insert system log in export_allocation_pdf: " . $e->getMessage());
}

// Output PDF to browser
$pdf->Output('I', 'agricultural_resource_allocation_report.pdf');
exit();
