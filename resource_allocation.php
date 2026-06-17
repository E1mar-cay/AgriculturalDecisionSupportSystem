<?php
/**
 * Resource Allocation Page
 * Smart Agricultural Decision Support System
 */
$page_title = "Resource Allocation";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

try {
    // Query aggregated data grouped by Barangay, Crop Type, and Season
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
    error_log("Database read error in resource_allocation: " . $e->getMessage());
    $allocations = [];
}

// Summary accumulator variables
$grand_total_area = 0;
$grand_total_seeds_kg = 0;
$grand_total_fertilizer_bags = 0;

// Pre-calculate sums for the summary cards
foreach ($allocations as $row) {
    $area = floatval($row['total_area']);
    $crop = strtolower(trim($row['crop_type']));
    
    // Seed conversion rate: Rice = 0.004 kg/sq.m, Corn = 0.002 kg/sq.m, Others = 0.003 kg/sq.m
    $seed_rate = 0.003;
    if ($crop === 'rice') {
        $seed_rate = 0.004;
    } elseif ($crop === 'corn') {
        $seed_rate = 0.002;
    }
    
    $seeds = $area * $seed_rate;
    // Fertilizer conversion rate: 0.0003 bags per square meter (3 bags per 10,000 sq.m)
    $fertilizer = $area * 0.0003;

    $grand_total_area += $area;
    $grand_total_seeds_kg += $seeds;
    $grand_total_fertilizer_bags += $fertilizer;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Estimated Resource Allocations</h4>
        <p class="text-muted small mb-0">Forecasted seed and fertilizer requirements calculated per barangay.</p>
    </div>
    <?php if (!empty($allocations)): ?>
        <a href="actions/export_allocation_pdf.php" target="_blank" class="btn btn-primary d-inline-flex align-items-center">
            <i class="bi bi-file-earmark-pdf me-2"></i> Export Official PDF Report
        </a>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Total farm area -->
    <div class="col-md-4">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">Total Farm Land Area</span>
            <h3 class="mb-0 fw-bold text-success"><?php echo number_format($grand_total_area, 1); ?> <span class="fs-6 fw-normal text-muted">sq.m</span></h3>
        </div>
    </div>
    <!-- Total seeds needed -->
    <div class="col-md-4">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">Total Seeds Required</span>
            <h3 class="mb-0 fw-bold text-success"><?php echo number_format($grand_total_seeds_kg, 2); ?> <span class="fs-6 fw-normal text-muted">kg</span></h3>
        </div>
    </div>
    <!-- Total fertilizer bags needed -->
    <div class="col-md-4">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">Total Fertilizer Required</span>
            <h3 class="mb-0 fw-bold text-success"><?php echo number_format($grand_total_fertilizer_bags, 2); ?> <span class="fs-6 fw-normal text-muted">bags</span></h3>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Allocation Details Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calculator me-2 text-success"></i>Estimated Resource Allocations per Barangay
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Crop Type</th>
                                <th>Season</th>
                                <th style="text-align: right;">Total Land Area (sq.m)</th>
                                <th style="text-align: right;">Farmers</th>
                                <th style="text-align: right; background-color: rgba(27, 94, 32, 0.02);">Seed Allocation (kg)</th>
                                <th style="text-align: right; background-color: rgba(27, 94, 32, 0.02);">Fertilizer Allocation (bags)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bi bi-file-earmark-bar-graph d-block fs-3 mb-2"></i>
                                        No data available to calculate resource allocations. Please upload a dataset in Data Management first.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allocations as $row): 
                                    $area = floatval($row['total_area']);
                                    $crop = strtolower(trim($row['crop_type']));
                                    
                                    // Seed conversion logic based on sq.m
                                    $seed_rate = 0.003;
                                    $seed_label = 'Standard (0.003 kg/sq.m)';
                                    if ($crop === 'rice') {
                                        $seed_rate = 0.004;
                                        $seed_label = 'Rice Rate (0.004 kg/sq.m)';
                                    } elseif ($crop === 'corn') {
                                        $seed_rate = 0.002;
                                        $seed_label = 'Corn Rate (0.002 kg/sq.m)';
                                    }
                                    
                                    $seed_alloc = $area * $seed_rate;
                                    $fertilizer_alloc = $area * 0.0003; // 3 bags per 10,000 sq.m
                                ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row['barangay']); ?></td>
                                        <td><?php echo htmlspecialchars($row['crop_type']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo ($row['season'] === 'Wet Season') ? 'bg-info-subtle text-info border border-info' : 'bg-warning-subtle text-warning-emphasis border border-warning'; ?> px-2 py-0.5" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars($row['season']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end font-monospace"><?php echo number_format($area, 1); ?></td>
                                        <td class="text-end font-monospace"><?php echo number_format($row['farmer_count']); ?></td>
                                        <td class="text-end font-monospace fw-semibold" style="background-color: rgba(27, 94, 32, 0.02);" title="<?php echo $seed_label; ?>">
                                            <?php echo number_format($seed_alloc, 1); ?>
                                        </td>
                                        <td class="text-end font-monospace fw-semibold" style="background-color: rgba(27, 94, 32, 0.02);" title="Fertilizer Rate (0.0003 bags/sq.m)">
                                            <?php echo number_format($fertilizer_alloc, 1); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12 text-muted" style="font-size: 0.75rem;">
        <i class="bi bi-info-circle-fill text-success me-1"></i>
        <strong>Calculation parameters:</strong> 
        Seed allocations are estimated at 0.004 kg/sq.m for Rice, 0.002 kg/sq.m for Corn, and 0.003 kg/sq.m fallback for other crops. 
        Fertilizer requirements are estimated at 0.0003 bags (50kg equivalent) per square meter.
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
