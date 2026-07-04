<?php
/**
 * Dashboard Page - Multi-Chart Agricultural DSS View
 * Smart Agricultural Decision Support System
 */
$page_title = "Dashboard";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

try {
    // 1. Fetch core agricultural stats
    $records_count = intval($pdo->query("SELECT COUNT(*) FROM tbl_rsbsa_data")->fetchColumn());
    $rules_count = intval($pdo->query("SELECT COUNT(*) FROM tbl_forecast_rules")->fetchColumn());
    $total_area = floatval($pdo->query("SELECT IFNULL(SUM(farm_size), 0) FROM tbl_rsbsa_data")->fetchColumn());
    $barangay_count = intval($pdo->query("SELECT COUNT(DISTINCT barangay) FROM tbl_rsbsa_data")->fetchColumn());
    
    // Average farm size calculation
    $avg_farm_size = $records_count > 0 ? ($total_area / $records_count) : 0.0;
    
    // 2. Fetch crop counts for Crop Doughnut
    $cropStmt = $pdo->query("SELECT crop_type, COUNT(*) as count FROM tbl_rsbsa_data GROUP BY crop_type ORDER BY count DESC");
    $crop_data = $cropStmt->fetchAll();
    
    // 3. Fetch top 5 barangays by land area for Horizontal Bar
    $brgyStmt = $pdo->query("SELECT barangay, SUM(farm_size) as area FROM tbl_rsbsa_data GROUP BY barangay ORDER BY area DESC LIMIT 5");
    $brgy_data = $brgyStmt->fetchAll();

    // 4. Fetch season area distribution for Season Doughnut
    $seasonStmt = $pdo->query("SELECT season, SUM(farm_size) as area FROM tbl_rsbsa_data GROUP BY season");
    $season_data = $seasonStmt->fetchAll();
    
    // 5. Parse rules to extract the top recommended/associated interventions
    $rulesStmt = $pdo->query("SELECT antecedents, consequents FROM tbl_forecast_rules");
    $rules_list = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

    $intervention_counts = [];
    foreach ($rules_list as $rule) {
        $all_items = array_merge(
            explode(', ', $rule['antecedents']),
            explode(', ', $rule['consequents'])
        );
        foreach ($all_items as $item) {
            if (str_starts_with($item, 'intervention_received:')) {
                $intervention = substr($item, strlen('intervention_received:'));
                if (!isset($intervention_counts[$intervention])) {
                    $intervention_counts[$intervention] = 0;
                }
                $intervention_counts[$intervention]++;
            }
        }
    }

    // Sort by count descending
    arsort($intervention_counts);
    $top_interventions = array_slice($intervention_counts, 0, 5, true);
    
    // Fetch top crop and top barangay labels
    $top_crop = !empty($crop_data) ? $crop_data[0]['crop_type'] : 'N/A';
    $top_barangay = !empty($brgy_data) ? $brgy_data[0]['barangay'] : 'N/A';
    $top_barangay_area = !empty($brgy_data) ? floatval($brgy_data[0]['area']) : 0.0;
    
} catch (\PDOException $e) {
    error_log("Database read error in dashboard: " . $e->getMessage());
    $records_count = $rules_count = $barangay_count = 0;
    $total_area = $avg_farm_size = 0.0;
    $top_crop = $top_barangay = 'N/A';
    $top_barangay_area = 0.0;
    $crop_data = $brgy_data = $season_data = $top_interventions = [];
}

// --- Phase 7 Map Data Processing ---
$map_barangays_json = [];
try {
    // 1. Fetch forecast rules mapping
    $mapRulesStmt = $pdo->query("SELECT antecedents, consequents FROM tbl_forecast_rules");
    $mapRules = $mapRulesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $crop_to_intervention = [];
    foreach ($mapRules as $rule) {
        $all_items = array_merge(
            explode(', ', $rule['antecedents']),
            explode(', ', $rule['consequents'])
        );
        $crop = null;
        $intervention = null;
        
        foreach ($all_items as $item) {
            if (str_starts_with($item, 'crop_type:')) {
                $crop = substr($item, strlen('crop_type:'));
            } elseif (str_starts_with($item, 'intervention_received:')) {
                $intervention = substr($item, strlen('intervention_received:'));
            }
        }
        if ($crop && $intervention) {
            $crop_to_intervention[strtolower($crop)] = $intervention;
        }
    }
    
    // Set fallback associations if rules are empty
    if (empty($crop_to_intervention)) {
        $crop_to_intervention = [
            'rice' => 'Fertilizer Distribution',
            'corn' => 'Seed Subsidy'
        ];
    }
    
    // 2. Fetch distinct barangays and their crop distributions
    $barangayDataStmt = $pdo->query("
        SELECT barangay, crop_type, SUM(farm_size) as crop_area, COUNT(*) as farmer_count
        FROM tbl_rsbsa_data
        GROUP BY barangay, crop_type
    ");
    $barangay_crop_rows = $barangayDataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $barangay_map_details = [];
    foreach ($barangay_crop_rows as $row) {
        $brgy = $row['barangay'];
        if (!isset($barangay_map_details[$brgy])) {
            $barangay_map_details[$brgy] = [
                'name' => $brgy,
                'total_area' => 0.0,
                'farmer_count' => 0,
                'fertilizer_area' => 0.0,
                'crop_sizes' => [],
                'crops' => []
            ];
        }
        
        $crop_raw = $row['crop_type'];
        $crop_lower = strtolower(trim($crop_raw));
        $area = floatval($row['crop_area']);
        
        $barangay_map_details[$brgy]['total_area'] += $area;
        $barangay_map_details[$brgy]['farmer_count'] += intval($row['farmer_count']);
        $barangay_map_details[$brgy]['crops'][] = $crop_raw;
        $barangay_map_details[$brgy]['crop_sizes'][$crop_raw] = $area;
        
        $mapped_intervention = $crop_to_intervention[$crop_lower] ?? '';
        if (str_contains(strtolower($mapped_intervention), 'fertilizer') || $crop_lower === 'rice') {
            $barangay_map_details[$brgy]['fertilizer_area'] += $area;
        }
    }
    
    foreach ($barangay_map_details as $brgy => $data) {
        arsort($data['crop_sizes']);
        $top_crop_in_brgy = key($data['crop_sizes']) ?? 'N/A';
        $top_crop_lower = strtolower(trim($top_crop_in_brgy));
        
        $top_forecasted = $crop_to_intervention[$top_crop_lower] ?? 'Standard Seeds';
        
        $map_barangays_json[$brgy] = [
            'name' => $brgy,
            'total_area' => $data['total_area'],
            'fertilizer_area' => $data['fertilizer_area'],
            'farmer_count' => $data['farmer_count'],
            'top_crop' => $top_crop_in_brgy,
            'top_intervention' => $top_forecasted,
            'crops_list' => implode(', ', array_unique($data['crops']))
        ];
    }
} catch (\PDOException $e) {
    error_log("Database error during map data processing: " . $e->getMessage());
}
?>

<!-- Include Chart.js & Leaflet & SweetAlert2 from CDNs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Dashboard Title Row -->
<div class="mb-4">
    <h4 class="mb-1 text-dark fw-bold">Dashboard Overview</h4>
    <p class="text-muted small mb-0">Real-time DSS statistics, geographic forecasts, and visual crop data.</p>
</div>

<div class="row g-4 mb-4">
    <!-- Stat Cards -->
    <div class="col-md-3">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">RSBSA Farmers</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <h3 class="mb-0 fw-bold" style="color: #1b5e20;"><?php echo number_format($records_count); ?></h3>
                <i class="bi bi-people-fill text-muted fs-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">Total Farm Area</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <h3 class="mb-0 fw-bold" style="color: #b45309;"><?php echo number_format($total_area, 1); ?> <span class="fs-6 fw-normal text-muted">sq.m</span></h3>
                <i class="bi bi-map-fill text-muted fs-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">Barangays Covered</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <h3 class="mb-0 fw-bold" style="color: #15803d;"><?php echo number_format($barangay_count); ?></h3>
                <i class="bi bi-geo-alt-fill text-muted fs-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <span class="text-muted small fw-semibold uppercase mb-1">AI Forecast Rules</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <h3 class="mb-0 fw-bold" style="color: #0f766e;"><?php echo number_format($rules_count); ?></h3>
                <i class="bi bi-diagram-3-fill text-muted fs-3"></i>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Mined Interventions Bar Chart & Crop diversity breakdown Doughnut -->
<div class="row g-4 mb-4">
    <!-- Top Interventions Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart-fill me-2" style="color: #1b5e20;"></i>Top Interventions Needed (Based on Mined Rules)
            </div>
            <div class="card-body">
                <?php if (empty($top_interventions)): ?>
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
                        <i class="bi bi-diagram-2 d-block fs-2 mb-2"></i>
                        <span>No recommendation rules mined yet. Run the AI analysis in Forecast Rules to display rules chart.</span>
                    </div>
                <?php else: ?>
                    <canvas id="interventionsChart" style="max-height: 320px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Crop Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart-fill me-2" style="color: #2e7d32;"></i>Crop Diversity Breakdown
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php if (empty($crop_data)): ?>
                    <div class="text-center text-muted">No crop data. Upload dataset.</div>
                <?php else: ?>
                    <canvas id="cropChart" style="max-height: 250px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Barangay Land Area horizontal chart & Season distribution Doughnut -->
<div class="row g-4 mb-4">
    <!-- Top 5 Barangay Land Area (Horizontal Bar Chart) -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-map me-2" style="color: #b45309;"></i>Farming Area per Barangay (Top 5, sq.m)
            </div>
            <div class="card-body">
                <?php if (empty($brgy_data)): ?>
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">No barangay land area statistics.</div>
                <?php else: ?>
                    <canvas id="brgyChart" style="max-height: 320px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Season Area Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-sun-fill me-2" style="color: #0f766e;"></i>Seasonal Distribution (Land Area, sq.m)
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php if (empty($season_data)): ?>
                    <div class="text-center text-muted">No seasonal data.</div>
                <?php else: ?>
                    <canvas id="seasonChart" style="max-height: 250px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row 3.5: Geographic Leaflet Map Card -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-geo-alt-fill me-2" style="color: #1b5e20;"></i>Geographic Crop Cultivation & Forecast Map</span>
                <span class="badge bg-success-subtle text-success border border-success px-2 py-0.5" style="font-size: 0.75rem;">
                    <i class="bi bi-circle-fill me-1 small"></i> Live Forecast Rules
                </span>
            </div>
            <div class="card-body p-0" style="position: relative;">
                <!-- Map div container -->
                <div id="leafletMap" style="height: 450px; width: 100%; z-index: 1;"></div>
                
                <!-- Legend overlay -->
                <div class="map-legend border bg-white p-3 shadow-sm" style="position: absolute; bottom: 20px; right: 20px; z-index: 1000; font-size: 0.8rem; min-width: 180px; border-radius: var(--radius-subtle); border-color: var(--border-color) !important;">
                    <h6 class="fw-bold mb-2 text-dark" style="font-size: 0.82rem;">Primary Crop Grown</h6>
                    <div class="d-flex align-items-center mb-1">
                        <span class="d-inline-block me-2" style="width: 14px; height: 14px; background-color: #2e7d32; border: 1px solid #1b5e20; border-radius: 2px;"></span>
                        <span>Rice (Green)</span>
                    </div>
                    <div class="d-flex align-items-center mb-1">
                        <span class="d-inline-block me-2" style="width: 14px; height: 14px; background-color: #f59e0b; border: 1px solid #b45309; border-radius: 2px;"></span>
                        <span>Corn (Yellow)</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="d-inline-block me-2" style="width: 14px; height: 14px; background-color: #94a3b8; border: 1px solid #475569; border-radius: 2px;"></span>
                        <span>Other / No Data</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 4: Agricultural Insights summary panel -->
<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightbulb-fill me-2" style="color: #1b5e20;"></i>Key Agricultural Insights Summary
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 border-end">
                        <span class="text-muted small d-block mb-1">Primary Crop Cultivated</span>
                        <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($top_crop); ?></span>
                    </div>
                    <div class="col-md-4 border-end">
                        <span class="text-muted small d-block mb-1">Farming Hotspot (Barangay)</span>
                        <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($top_barangay); ?></span>
                        <span class="d-block text-muted small mt-1">Total area: <?php echo number_format($top_barangay_area, 1); ?> sq.m</span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block mb-1">Average Land Area per Farmer</span>
                        <span class="fw-bold text-dark fs-5"><?php echo number_format($avg_farm_size, 1); ?> sq.m</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Configurations JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Shared chart settings variables
    const textFont = { family: 'Inter', size: 11 };
    
    // 1. Configure Interventions Needed Chart (Vertical Bar Chart)
    const interCtx = document.getElementById('interventionsChart');
    if (interCtx) {
        const interventionLabels = <?php echo json_encode(array_keys($top_interventions)); ?>;
        const interventionCounts = <?php echo json_encode(array_values($top_interventions)); ?>;
        
        new Chart(interCtx, {
            type: 'bar',
            data: {
                labels: interventionLabels,
                datasets: [{
                    label: 'Rule Occurrences',
                    data: interventionCounts,
                    backgroundColor: '#1b5e20', // Primary Theme Forest Green
                    borderWidth: 0,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a221e',
                        titleFont: { family: 'Inter', size: 12 },
                        bodyFont: { family: 'Inter', size: 11 },
                        padding: 8,
                        cornerRadius: 4,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: textFont
                        },
                        grid: { color: '#e9ecef', drawTicks: false },
                        border: { dash: [4, 4] }
                    },
                    x: {
                        ticks: { font: textFont },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Configure Crop Diversity Breakdown Chart (Doughnut Chart)
    const cropCtx = document.getElementById('cropChart');
    if (cropCtx) {
        const cropLabels = <?php echo json_encode(array_column($crop_data, 'crop_type')); ?>;
        const cropCounts = <?php echo json_encode(array_column($crop_data, 'count')); ?>;
        
        new Chart(cropCtx, {
            type: 'doughnut',
            data: {
                labels: cropLabels,
                datasets: [{
                    data: cropCounts,
                    // Harmonious agricultural crop colors
                    backgroundColor: [
                        '#1b5e20', // Rice (Forest Green)
                        '#f59e0b', // Corn (Maize Gold)
                        '#6b7280'  // Others (Slate Gray)
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: textFont, padding: 8 } }
                },
                cutout: '65%'
            }
        });
    }

    // 3. Configure Barangay Area Chart (Horizontal Bar Chart)
    const brgyCtx = document.getElementById('brgyChart');
    if (brgyCtx) {
        const brgyLabels = <?php echo json_encode(array_column($brgy_data, 'barangay')); ?>;
        const brgyAreas = <?php echo json_encode(array_column($brgy_data, 'area')); ?>;
        
        new Chart(brgyCtx, {
            type: 'bar',
            data: {
                labels: brgyLabels,
                datasets: [{
                    label: 'Total Farm Area (sq.m)',
                    data: brgyAreas,
                    backgroundColor: '#b45309', // Earthy Soil Brown
                    borderWidth: 0,
                    borderRadius: 3
                }]
            },
            options: {
                indexAxis: 'y', // Makes the bar chart horizontal
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a221e',
                        titleFont: { family: 'Inter', size: 12 },
                        bodyFont: { family: 'Inter', size: 11 },
                        padding: 8,
                        cornerRadius: 4,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { font: textFont },
                        grid: { color: '#e9ecef', drawTicks: false },
                        border: { dash: [4, 4] }
                    },
                    y: {
                        ticks: { font: textFont },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 4. Configure Season Distribution Chart (Doughnut Chart)
    const seasonCtx = document.getElementById('seasonChart');
    if (seasonCtx) {
        const seasonLabels = <?php echo json_encode(array_column($season_data, 'season')); ?>;
        const seasonAreas = <?php echo json_encode(array_column($season_data, 'area')); ?>;
        
        new Chart(seasonCtx, {
            type: 'doughnut',
            data: {
                labels: seasonLabels,
                datasets: [{
                    data: seasonAreas,
                    // Dynamic Season Colors: Rain Teal for Wet Season, Warm Gold for Dry Season
                    backgroundColor: [
                        '#0f766e', // Wet Season (Deep Teal)
                        '#f59e0b'  // Dry Season (Maize Gold)
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: textFont, padding: 8 } }
                },
                cutout: '65%'
            }
        });
    }

    // 5. Initialize Leaflet Map
    const mapContainer = document.getElementById('leafletMap');
    if (mapContainer) {
        // Center on Cabatuan coordinates: [16.9589, 121.6692]
        const map = L.map('leafletMap', {
            center: [16.9589, 121.6692],
            zoom: 13,
            scrollWheelZoom: false
        });

        // Add OSM tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Map data from PHP
        const mapBarangays = <?php echo json_encode($map_barangays_json); ?>;

        // Coordinates dictionary for mock barangay polygons
        const barangayPolygons = {
            // Database-stored barangays (designed to represent contiguous shapes near town center and west)
            "Poblacion": [
                [16.963, 121.663],
                [16.965, 121.675],
                [16.957, 121.677],
                [16.954, 121.666],
                [16.958, 121.659]
            ],
            "Poblacion Sur": [
                [16.954, 121.666],
                [16.957, 121.677],
                [16.942, 121.678],
                [16.941, 121.661]
            ],
            "Balingasag": [
                [16.965, 121.675],
                [16.978, 121.676],
                [16.975, 121.662],
                [16.963, 121.663]
            ],
            "Janipaan": [
                [16.958, 121.659],
                [16.954, 121.666],
                [16.941, 121.661],
                [16.943, 121.650],
                [16.955, 121.648]
            ],
            "Morcillas": [
                [16.975, 121.662],
                [16.963, 121.663],
                [16.958, 121.659],
                [16.955, 121.648],
                [16.968, 121.649],
                [16.972, 121.655]
            ],
            "Tupol": [
                [16.965, 121.675],
                [16.968, 121.692],
                [16.950, 121.691],
                [16.957, 121.677]
            ],
            "Talangnan": [
                [16.957, 121.677],
                [16.950, 121.691],
                [16.938, 121.690],
                [16.942, 121.678]
            ],
            // Alinguigan 2nd is in Ilagan City, Isabela (outside of Cabatuan)
            "Alinguigan 2nd": [
                [17.120, 121.870],
                [17.135, 121.872],
                [17.130, 121.885],
                [17.118, 121.882]
            ],
            "San Jose": [
                [16.938, 121.615],
                [16.952, 121.616],
                [16.953, 121.628],
                [16.940, 121.629]
            ],
            // Cabatuan actual/test barangays (realistic agricultural zone overlays in the west)
            "San Antonio": [
                [16.972, 121.625],
                [16.985, 121.627],
                [16.981, 121.642],
                [16.969, 121.640]
            ],
            "Diamantina": [
                [16.969, 121.640],
                [16.970, 121.626],
                [16.953, 121.628],
                [16.955, 121.642]
            ],
            "Curingan": [
                [16.955, 121.642],
                [16.953, 121.628],
                [16.940, 121.630],
                [16.942, 121.643]
            ],
            "Culing Centro": [
                [16.955, 121.642],
                [16.958, 121.659],
                [16.946, 121.658],
                [16.942, 121.643]
            ],
            "La Paz": [
                [16.969, 121.640],
                [16.968, 121.655],
                [16.958, 121.659],
                [16.955, 121.642]
            ]
        };

        function getPolygonColor(cropType) {
            const crop = String(cropType).toLowerCase().trim();
            if (crop === 'rice') return '#2e7d32';      // Green
            if (crop === 'corn') return '#f59e0b';      // Maize Gold
            return '#94a3b8';                           // Slate Grey
        }

        function getPolygonBorderColor(cropType) {
            const crop = String(cropType).toLowerCase().trim();
            if (crop === 'rice') return '#1b5e20';
            if (crop === 'corn') return '#b45309';
            return '#475569';
        }

        // Fallback coordinates generator for external or unrecognized barangays (guarantees display on map)
        function getFallbackPolygon(name, index) {
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            // Generate offset based on hash, centered around Cabatuan
            const latOffset = ((hash & 0xFF) / 255.0 - 0.5) * 0.15; // slightly wider bounds
            const lngOffset = (((hash >> 8) & 0xFF) / 255.0 - 0.5) * 0.15;
            const centerLat = 16.9589 + latOffset;
            const centerLng = 121.6692 + lngOffset;
            const size = 0.007; // standard polygon size
            return [
                [centerLat - size * 0.8, centerLng - size * 0.8],
                [centerLat + size * 0.9, centerLng - size * 0.6],
                [centerLat + size * 0.7, centerLng + size * 0.8],
                [centerLat - size * 0.7, centerLng + size * 0.9],
                [centerLat - size * 0.9, centerLng - size * 0.2]
            ];
        }

        const polygonLayers = [];

        Object.keys(mapBarangays).forEach(function(key, idx) {
            const brgy = mapBarangays[key];
            let coordinates = barangayPolygons[brgy.name];

            // If not pre-defined, generate a stable fallback polygon to ensure it is always colored and drawn
            if (!coordinates) {
                coordinates = getFallbackPolygon(brgy.name, idx);
            }

            if (coordinates) {
                const color = getPolygonColor(brgy.top_crop);
                const borderColor = getPolygonBorderColor(brgy.top_crop);
                
                const polygon = L.polygon(coordinates, {
                    color: borderColor,
                    weight: 1.5,
                    fillColor: color,
                    fillOpacity: 0.65
                }).addTo(map);

                polygon.on('mouseover', function(e) {
                    this.setStyle({
                        fillOpacity: 0.85,
                        weight: 2,
                        color: '#051006'
                    });
                });
                
                polygon.on('mouseout', function(e) {
                    this.setStyle({
                        fillOpacity: 0.65,
                        weight: 1.5,
                        color: borderColor
                    });
                });

                const tooltipContent = `
                    <div style="font-family: 'Inter', sans-serif; padding: 2px; min-width: 160px;">
                        <h6 style="margin: 0 0 6px 0; font-weight: 700; color: #1b5e20; font-size: 0.85rem;">${brgy.name}</h6>
                        <table style="width: 100%; font-size: 0.72rem; border-collapse: collapse;">
                            <tr>
                                <td style="color: #666; padding: 2px 0;">Total Area:</td>
                                <td style="font-weight: 600; text-align: right; padding: 2px 0;">${Number(brgy.total_area).toLocaleString()} sq.m</td>
                            </tr>
                            <tr>
                                <td style="color: #666; padding: 2px 0;">Farmers:</td>
                                <td style="font-weight: 600; text-align: right; padding: 2px 0;">${brgy.farmer_count}</td>
                            </tr>
                            <tr>
                                <td style="color: #666; padding: 2px 0;">Primary Crop:</td>
                                <td style="font-weight: 600; text-align: right; padding: 2px 0;">${brgy.top_crop}</td>
                            </tr>
                            <tr style="border-top: 1px solid #eee;">
                                <td style="color: #1b5e20; font-weight: 600; padding: 4px 0 0 0;">Forecasted Alert:</td>
                                <td style="font-weight: 700; text-align: right; color: #1b5e20; padding: 4px 0 0 0;">${brgy.top_intervention}</td>
                            </tr>
                        </table>
                    </div>
                `;
                
                polygon.bindTooltip(tooltipContent, {
                    sticky: true,
                    direction: 'top',
                    className: 'custom-map-tooltip'
                });

                polygonLayers.push(polygon);
            }
        });

        // Automatically adjust map boundaries to fit all rendered polygons (including external ones)
        if (polygonLayers.length > 0) {
            const group = new L.featureGroup(polygonLayers);
            map.fitBounds(group.getBounds(), { padding: [40, 40] });
        }
    }

    // 6. Notify Workers AJAX Trigger
});
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
