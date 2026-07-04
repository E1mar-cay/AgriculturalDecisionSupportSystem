<?php
/**
 * Forecast Rules Page
 * Smart Agricultural Decision Support System
 */
$page_title = "Forecast Rules";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

// Fetch all generated association rules
try {
    $stmt = $pdo->query("
        SELECT antecedents, consequents, support, confidence, lift 
        FROM tbl_forecast_rules 
        ORDER BY confidence DESC, lift DESC
    ");
    $rules = $stmt->fetchAll();
    
    // Fetch system settings for support & confidence thresholds
    $settingsStmt = $pdo->query("SELECT setting_name, setting_value FROM tbl_system_settings");
    $settings_raw = $settingsStmt->fetchAll();
    $min_support = 0.10;
    $min_confidence = 0.50;
    foreach ($settings_raw as $s) {
        if ($s['setting_name'] === 'min_support') {
            $min_support = floatval($s['setting_value']);
        }
        if ($s['setting_name'] === 'min_confidence') {
            $min_confidence = floatval($s['setting_value']);
        }
    }
} catch (\PDOException $e) {
    error_log("Database read error in forecast_rules: " . $e->getMessage());
    $rules = [];
    $min_support = 0.10;
    $min_confidence = 0.50;
}
?>

<!-- SweetAlert2 CDNs -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row g-4 mb-4 align-items-center">
    <!-- Header panel with trigger buttons and thresholds info -->
    <div class="col-md-8">
        <p class="text-muted mb-0">
            Association rule mining is applied to target patterns between <strong>Barangays, Crops, Seasons,</strong> and <strong>Interventions</strong>.
        </p>
    </div>
    <div class="col-md-4 text-md-end">
        <button type="button" class="btn btn-primary" id="runEngineBtn">
            <i class="bi bi-cpu-fill me-1"></i>Run AI Analysis
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration panel -->
    <div class="col-lg-3">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-sliders me-2 text-success"></i>Current Parameters
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">Minimum Support</span>
                    <span class="fw-bold text-dark fs-5"><?php echo ($min_support * 100); ?>%</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">Minimum Confidence</span>
                    <span class="fw-bold text-dark fs-5"><?php echo ($min_confidence * 100); ?>%</span>
                </div>
                <?php if ($_SESSION['role'] === 'System Admin'): ?>
                    <a href="settings.php" class="btn btn-sm btn-outline-primary w-100 mt-2">
                        <i class="bi bi-gear-fill me-1"></i>Adjust Parameters
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-success"></i>Understanding Metrics
            </div>
            <div class="card-body" style="font-size: 0.82rem; line-height: 1.4;">
                <p><strong>Support:</strong> The frequency of the rule in the dataset.</p>
                <p><strong>Confidence:</strong> The conditional probability that the consequent is true given the antecedent.</p>
                <p class="mb-0"><strong>Lift:</strong> The ratio of confidence to expected support. A Lift > 1 indicates a strong correlation between the items.</p>
            </div>
        </div>
    </div>

    <!-- Mined Rules Table -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-grid-3x3-gap-fill me-2 text-success"></i>Mined Association Rules (<?php echo count($rules); ?>)</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="sticky-top bg-light" style="z-index: 5;">
                            <tr>
                                <th>Antecedents (If)</th>
                                <th style="width: 40px; text-align: center;"><i class="bi bi-arrow-right"></i></th>
                                <th>Consequents (Then)</th>
                                <th style="width: 100px; text-align: right;">Support</th>
                                <th style="width: 100px; text-align: right;">Confidence</th>
                                <th style="width: 100px; text-align: right;">Lift</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rules)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-clipboard-x d-block fs-3 mb-2"></i>
                                        No association rules found. Click "Run AI Analysis" to generate.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $ant_items = explode(', ', $rule['antecedents']);
                                            foreach ($ant_items as $item) {
                                                $clean_item = $item;
                                                if (str_starts_with($item, 'barangay:')) {
                                                    $clean_item = 'Barangay: ' . substr($item, strlen('barangay:'));
                                                } elseif (str_starts_with($item, 'crop_type:')) {
                                                    $clean_item = 'Crop: ' . substr($item, strlen('crop_type:'));
                                                } elseif (str_starts_with($item, 'season:')) {
                                                    $clean_item = 'Season: ' . substr($item, strlen('season:'));
                                                } elseif (str_starts_with($item, 'intervention_received:')) {
                                                    $clean_item = 'Intervention: ' . substr($item, strlen('intervention_received:'));
                                                } elseif (str_starts_with($item, 'fertilizer_type:')) {
                                                    $clean_item = 'Fertilizer: ' . substr($item, strlen('fertilizer_type:'));
                                                } elseif (str_starts_with($item, 'application_type:')) {
                                                    $clean_item = 'Application: ' . substr($item, strlen('application_type:'));
                                                }
                                                echo '<span class="badge bg-light text-dark border me-1 my-1 px-2.5 py-1.5 font-monospace">' . htmlspecialchars($clean_item) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center text-muted"><i class="bi bi-arrow-right-short fs-5"></i></td>
                                        <td>
                                            <?php 
                                            $con_items = explode(', ', $rule['consequents']);
                                            foreach ($con_items as $item) {
                                                $clean_item = $item;
                                                if (str_starts_with($item, 'barangay:')) {
                                                    $clean_item = 'Barangay: ' . substr($item, strlen('barangay:'));
                                                } elseif (str_starts_with($item, 'crop_type:')) {
                                                    $clean_item = 'Crop: ' . substr($item, strlen('crop_type:'));
                                                } elseif (str_starts_with($item, 'season:')) {
                                                    $clean_item = 'Season: ' . substr($item, strlen('season:'));
                                                } elseif (str_starts_with($item, 'intervention_received:')) {
                                                    $clean_item = 'Intervention: ' . substr($item, strlen('intervention_received:'));
                                                } elseif (str_starts_with($item, 'fertilizer_type:')) {
                                                    $clean_item = 'Fertilizer: ' . substr($item, strlen('fertilizer_type:'));
                                                } elseif (str_starts_with($item, 'application_type:')) {
                                                    $clean_item = 'Application: ' . substr($item, strlen('application_type:'));
                                                }
                                                echo '<span class="badge bg-success-subtle text-success border border-success me-1 my-1 px-2.5 py-1.5 font-monospace">' . htmlspecialchars($clean_item) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end font-monospace"><?php echo number_format($rule['support'], 4); ?></td>
                                        <td class="text-end font-monospace fw-semibold"><?php echo number_format($rule['confidence'], 4); ?></td>
                                        <td class="text-end font-monospace <?php echo ($rule['lift'] > 1.0) ? 'text-success fw-bold' : ''; ?>"><?php echo number_format($rule['lift'], 4); ?></td>
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

<!-- AJAX and Loader JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const runEngineBtn = document.getElementById("runEngineBtn");
    
    if (runEngineBtn) {
        runEngineBtn.addEventListener("click", function () {
            // Show loader
            Swal.fire({
                title: 'Executing Apriori Engine',
                text: 'The machine learning script is analyzing the dataset and mining association rules. Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'card border shadow-sm p-4'
                }
            });

            // Trigger analysis via AJAX (Fetch)
            fetch('actions/trigger_apriori.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response error.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        let aprioriTime = data.apriori_time_ms ? parseFloat(data.apriori_time_ms).toFixed(2) : 'N/A';
                        let fpgrowthTime = data.fpgrowth_time_ms ? parseFloat(data.fpgrowth_time_ms).toFixed(2) : 'N/A';
                        Swal.fire({
                            icon: 'success',
                            title: 'Analysis Complete',
                            html: `<div class="text-start">` +
                                  `<p>${data.message}</p>` +
                                  `<hr>` +
                                  `<p class="mb-1"><strong>Execution Benchmarks:</strong></p>` +
                                  `<ul class="list-unstyled font-monospace small mb-0">` +
                                  `<li>⚡ Apriori Engine: ${aprioriTime} ms</li>` +
                                  `<li>⚡ FP-Growth Engine: ${fpgrowthTime} ms</li>` +
                                  `</ul>` +
                                  `</div>`,
                            confirmButtonColor: '#1b5e20'
                        }).then(() => {
                            // Reload page to display new rules
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Analysis Issue',
                            text: data.message,
                            confirmButtonColor: '#1b5e20'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Execution Error',
                        text: 'An error occurred while calling the analysis script: ' + error.message,
                        confirmButtonColor: '#1b5e20'
                    });
                });
        });
    }
});
</script>



<?php
include_once __DIR__ . '/includes/footer.php';
?>
