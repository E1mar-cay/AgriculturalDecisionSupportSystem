<?php
/**
 * System Settings Page - Apriori Parameter Tuning
 * Smart Agricultural Decision Support System
 */
$page_title = "System Settings";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

// Authorization check: only System Admin allowed
if ($_SESSION['role'] !== 'System Admin') {
    echo '
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger py-3">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <span class="fw-semibold">Access Denied:</span> You do not have permission to view or manage system settings.
            </div>
            <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-house-door me-1"></i>Return to Dashboard</a>
        </div>
    </div>';
    include_once __DIR__ . '/includes/footer.php';
    exit();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current parameters from tbl_system_settings
$min_support = 0.10;
$min_confidence = 0.50;

try {
    $settingsStmt = $pdo->query("SELECT setting_name, setting_value FROM tbl_system_settings");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (isset($settings['min_support'])) {
        $min_support = floatval($settings['min_support']);
    }
    if (isset($settings['min_confidence'])) {
        $min_confidence = floatval($settings['min_confidence']);
    }
} catch (\PDOException $e) {
    error_log("Database read error in settings: " . $e->getMessage());
}
?>

<!-- SweetAlert2 CSS & JS CDNs -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row g-4">
    <!-- Configuration Panel -->
    <div class="col-lg-8 col-xl-7 mx-auto">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-sliders me-2 style-success" style="color: #1b5e20;"></i>AI Apriori Parameter Tuning
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4" style="font-size: 0.9rem;">
                    Adjust the sensitivity thresholds of the Apriori association rule mining engine. Changes will apply to future AI analysis executions.
                </p>

                <form action="actions/update_settings.php" method="POST" class="needs-validation" novalidate>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <!-- Minimum Support (Popularity Filter) -->
                    <div class="mb-4 p-3 border rounded bg-light" style="border-color: #e2e8f0 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="min_support_num" class="form-label mb-0 fw-semibold text-dark">Minimum Support (Popularity Filter)</label>
                            <div class="input-group input-group-sm" style="width: 100px;">
                                <input type="number" class="form-control text-end" id="min_support_num" step="0.01" min="0.01" max="0.99" required value="<?php echo $min_support; ?>">
                                <span class="input-group-text font-monospace" style="font-size: 0.75rem;">val</span>
                            </div>
                        </div>
                        <input type="range" class="form-range" name="min_support" id="min_support" min="0.01" max="0.99" step="0.01" value="<?php echo $min_support; ?>">
                        <div class="d-flex justify-content-between text-muted small mt-1 font-monospace" style="font-size: 0.72rem;">
                            <span>1% (0.01) - Highly Sensitive</span>
                            <span id="support_percentage" class="fw-semibold text-success"><?php echo ($min_support * 100); ?>% popularity</span>
                            <span>99% (0.99) - Restrictive</span>
                        </div>
                        <div class="form-text text-muted small mt-2" style="font-size: 0.78rem;">
                            <i class="bi bi-info-circle me-1"></i> Sets the minimum percentage of records a combination must appear in to be considered. Lowering this value mines more rules (including rare patterns), but may increase noise.
                        </div>
                    </div>

                    <!-- Minimum Confidence (Reliability Filter) -->
                    <div class="mb-4 p-3 border rounded bg-light" style="border-color: #e2e8f0 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="min_confidence_num" class="form-label mb-0 fw-semibold text-dark">Minimum Confidence (Reliability Filter)</label>
                            <div class="input-group input-group-sm" style="width: 100px;">
                                <input type="number" class="form-control text-end" id="min_confidence_num" step="0.01" min="0.01" max="1.00" required value="<?php echo $min_confidence; ?>">
                                <span class="input-group-text font-monospace" style="font-size: 0.75rem;">val</span>
                            </div>
                        </div>
                        <input type="range" class="form-range" name="min_confidence" id="min_confidence" min="0.01" max="1.00" step="0.01" value="<?php echo $min_confidence; ?>">
                        <div class="d-flex justify-content-between text-muted small mt-1 font-monospace" style="font-size: 0.72rem;">
                            <span>1% (0.01) - Low Predictability</span>
                            <span id="confidence_percentage" class="fw-semibold text-success"><?php echo ($min_confidence * 100); ?>% predictability</span>
                            <span>100% (1.00) - Strict Predictability</span>
                        </div>
                        <div class="form-text text-muted small mt-2" style="font-size: 0.78rem;">
                            <i class="bi bi-info-circle me-1"></i> Sets the minimum predictability threshold. A rule mapping "Crop X -> Intervention Y" must hold true in at least this percentage of cases to be displayed.
                        </div>
                    </div>

                    <!-- Buttons Group -->
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="forecast_rules.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Return to Rules</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Parameters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const supportRange = document.getElementById("min_support");
    const supportNum = document.getElementById("min_support_num");
    const supportText = document.getElementById("support_percentage");

    const confidenceRange = document.getElementById("min_confidence");
    const confidenceNum = document.getElementById("min_confidence_num");
    const confidenceText = document.getElementById("confidence_percentage");

    // 1. Synchronize Support Range -> Number
    supportRange.addEventListener("input", function() {
        const val = parseFloat(this.value);
        supportNum.value = val.toFixed(2);
        supportText.textContent = (val * 100).toFixed(0) + "% popularity";
    });

    // Synchronize Support Number -> Range
    supportNum.addEventListener("input", function() {
        let val = parseFloat(this.value);
        if (isNaN(val)) val = 0.10;
        val = Math.max(0.01, Math.min(0.99, val));
        supportRange.value = val;
        supportText.textContent = (val * 100).toFixed(0) + "% popularity";
    });

    supportNum.addEventListener("blur", function() {
        let val = parseFloat(this.value);
        if (isNaN(val)) val = 0.10;
        val = Math.max(0.01, Math.min(0.99, val));
        this.value = val.toFixed(2);
    });

    // 2. Synchronize Confidence Range -> Number
    confidenceRange.addEventListener("input", function() {
        const val = parseFloat(this.value);
        confidenceNum.value = val.toFixed(2);
        confidenceText.textContent = (val * 100).toFixed(0) + "% predictability";
    });

    // Synchronize Confidence Number -> Range
    confidenceNum.addEventListener("input", function() {
        let val = parseFloat(this.value);
        if (isNaN(val)) val = 0.50;
        val = Math.max(0.01, Math.min(1.00, val));
        confidenceRange.value = val;
        confidenceText.textContent = (val * 100).toFixed(0) + "% predictability";
    });

    confidenceNum.addEventListener("blur", function() {
        let val = parseFloat(this.value);
        if (isNaN(val)) val = 0.50;
        val = Math.max(0.01, Math.min(1.00, val));
        this.value = val.toFixed(2);
    });
});
</script>

<!-- SweetAlert2 Trigger Alert (settings redirection status) -->
<?php if (isset($_SESSION['settings_status'])): ?>
<script>
Swal.fire({
    icon: '<?php echo $_SESSION['settings_status']['icon']; ?>',
    title: '<?php echo htmlspecialchars($_SESSION['settings_status']['title']); ?>',
    text: '<?php echo htmlspecialchars($_SESSION['settings_status']['text']); ?>',
    confirmButtonColor: '#1b5e20'
});
</script>
<?php unset($_SESSION['settings_status']); endif; ?>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
