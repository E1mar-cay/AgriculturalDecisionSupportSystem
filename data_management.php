<?php
/**
 * Data Management Page - Full CRUD with Search, Pagination and CSV Upload
 * Smart Agricultural Decision Support System
 */
$page_title = "Data Management";
include_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_connect.php';

// Ensure CSRF token is available
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Fetch distinct barangays for filter dropdown
try {
    $distinctBrgyStmt = $pdo->query("SELECT DISTINCT barangay FROM tbl_rsbsa_data ORDER BY barangay ASC");
    $barangays_list = $distinctBrgyStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (\PDOException $e) {
    error_log("Failed to fetch distinct barangays: " . $e->getMessage());
    $barangays_list = [];
}

// 2. Setup pagination, search, and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$brgy_filter = isset($_GET['brgy_filter']) ? trim($_GET['brgy_filter']) : '';
$season_filter = isset($_GET['season_filter']) ? trim($_GET['season_filter']) : '';

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(barangay LIKE :search OR crop_type LIKE :search OR intervention_received LIKE :search OR fertilizer_type LIKE :search OR application_type LIKE :search)";
    $params['search'] = "%$search%";
}
if ($brgy_filter !== '') {
    $where_clauses[] = "barangay = :brgy_filter";
    $params['brgy_filter'] = $brgy_filter;
}
if ($season_filter !== '') {
    $where_clauses[] = "season = :season_filter";
    $params['season_filter'] = $season_filter;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch total records & filtered records counts
try {
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_rsbsa_data");
    $total_records = intval($totalStmt->fetchColumn());

    $filteredStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_rsbsa_data $where_sql");
    $filteredStmt->execute($params);
    $filtered_records = intval($filteredStmt->fetchColumn());
} catch (\PDOException $e) {
    error_log("Database count error in data_management: " . $e->getMessage());
    $total_records = 0;
    $filtered_records = 0;
}

$limit = 10;
$total_pages = ceil($filtered_records / $limit);
$page = max(1, min($total_pages, $page));
$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

// Fetch filtered records for the current page
try {
    $data_sql = "
        SELECT id, barangay, crop_type, farm_size, season, intervention_received, fertilizer_type, application_type 
        FROM tbl_rsbsa_data 
        $where_sql 
        ORDER BY id DESC 
        LIMIT $limit OFFSET $offset
    ";
    $dataStmt = $pdo->prepare($data_sql);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Database data fetch error in data_management: " . $e->getMessage());
    $records = [];
}
?>

<!-- SweetAlert2 CSS & JS CDNs -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Row 1: Metrics Overview and Filters Bar -->
<div class="row g-4 mb-4">
    <!-- Quick Stats -->
    <div class="col-md-3">
        <div class="card p-3 h-100 justify-content-center">
            <span class="text-muted small fw-semibold uppercase mb-1">Total Dataset Records</span>
            <h3 class="mb-0 fw-bold" style="color: #2b5c8f;"><?php echo number_format($total_records); ?></h3>
        </div>
    </div>
    
    <!-- Filters and Search panel -->
    <div class="col-md-9">
        <div class="card p-3">
            <form method="GET" action="data_management.php" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label small fw-semibold text-muted mb-1">Search Barangay/Crop</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="search" class="form-control form-control-sm border-start-0" placeholder="Type to search..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="brgy_filter" class="form-label small fw-semibold text-muted mb-1">Barangay</label>
                    <select name="brgy_filter" id="brgy_filter" class="form-select form-select-sm">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays_list as $brgy): ?>
                            <option value="<?php echo htmlspecialchars($brgy); ?>" <?php echo ($brgy_filter === $brgy) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brgy); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="season_filter" class="form-label small fw-semibold text-muted mb-1">Season</label>
                    <select name="season_filter" id="season_filter" class="form-select form-select-sm">
                        <option value="">All Seasons</option>
                        <option value="Wet Season" <?php echo ($season_filter === 'Wet Season') ? 'selected' : ''; ?>>Wet Season</option>
                        <option value="Dry Season" <?php echo ($season_filter === 'Dry Season') ? 'selected' : ''; ?>>Dry Season</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                    <?php if ($search !== '' || $brgy_filter !== '' || $season_filter !== ''): ?>
                        <a href="data_management.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: CSV Upload & Controls -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-arrow-up me-2" style="color: #2b5c8f;"></i>Upload Dataset</span>
            </div>
            <div class="card-body">
                <form action="actions/clean_and_import.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-zone" id="upload-zone">
                        <i class="bi bi-cloud-arrow-up upload-icon d-block"></i>
                        <span class="fw-semibold text-dark d-block mb-1">Drag & drop your CSV here</span>
                        <span class="text-muted small d-block mb-3">or click to browse local files</span>
                        <span class="badge bg-light text-muted border py-1.5 px-2.5" style="font-size: 0.75rem;">Only CSV (max 5MB)</span>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </div>

                    <!-- Selected File Info -->
                    <div id="file-info" class="mt-3 p-2 border bg-light d-none" style="border-radius: var(--radius-subtle);">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-truncate me-2 fw-medium"><i class="bi bi-file-earmark-check-fill text-success me-1"></i><span id="selected-file-name">filename.csv</span></span>
                            <button type="button" class="btn-close" id="clear-file" aria-label="Clear selected file" style="font-size: 0.75rem;"></button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4 py-2" id="submit-btn" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Clean and Import Data
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Database Records List (CRUD Table) -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-database me-2" style="color: #bc6c25;"></i>RSBSA Records Database</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btn-batch-delete" onclick="batchDeleteRecords()">
                        <i class="bi bi-trash3 me-1"></i>Delete Selected (<span id="select-count">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Record
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="ps-3">
                                    <input class="form-check-input" type="checkbox" id="check-all" aria-label="Select all rows">
                                </th>
                                <th>Barangay</th>
                                <th>Crop Type</th>
                                <th>Farm Size (sq.m)</th>
                                <th>Season</th>
                                <th>Intervention</th>
                                <th>Fertilizer Type</th>
                                <th>Application Type</th>
                                <th class="text-end px-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                        No matching records found in the database.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $rec): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $rec['id']; ?>" aria-label="Select row">
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($rec['barangay']); ?></td>
                                        <td><?php echo htmlspecialchars($rec['crop_type']); ?></td>
                                        <td><?php echo number_format($rec['farm_size'], 1); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo ($rec['season'] === 'Wet Season') ? 'bg-info-subtle text-info border border-info' : 'bg-warning-subtle text-warning-emphasis border border-warning'; ?> px-2.5 py-1">
                                                <?php echo htmlspecialchars($rec['season']); ?>
                                            </span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($rec['intervention_received']); ?>">
                                            <?php echo htmlspecialchars($rec['intervention_received']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($rec['fertilizer_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($rec['application_type'] ?? 'N/A'); ?></td>
                                        <td class="text-end px-3">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        data-bs-toggle="modal" data-bs-target="#editRecordModal" 
                                                        data-id="<?php echo $rec['id']; ?>"
                                                        data-barangay="<?php echo htmlspecialchars($rec['barangay']); ?>"
                                                        data-crop="<?php echo htmlspecialchars($rec['crop_type']); ?>"
                                                        data-size="<?php echo htmlspecialchars($rec['farm_size']); ?>"
                                                        data-season="<?php echo htmlspecialchars($rec['season']); ?>"
                                                        data-intervention="<?php echo htmlspecialchars($rec['intervention_received']); ?>"
                                                        data-fertilizer="<?php echo htmlspecialchars($rec['fertilizer_type'] ?? ''); ?>"
                                                        data-application="<?php echo htmlspecialchars($rec['application_type'] ?? ''); ?>"
                                                        title="Edit Record">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteRecord(<?php echo $rec['id']; ?>)" title="Delete Record">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Card Footer Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between py-3">
                    <span class="small text-muted">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $filtered_records); ?> of <?php echo number_format($filtered_records); ?> records
                    </span>
                    <nav aria-label="Records pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Button -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&brgy_filter=<?php echo urlencode($brgy_filter); ?>&season_filter=<?php echo urlencode($season_filter); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&brgy_filter=<?php echo urlencode($brgy_filter); ?>&season_filter=<?php echo urlencode($season_filter); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Button -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&brgy_filter=<?php echo urlencode($brgy_filter); ?>&season_filter=<?php echo urlencode($season_filter); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODALS SECTION                                           -->
<!-- ======================================================== -->

<!-- Add Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: var(--radius-subtle); overflow: hidden;">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-dark" id="addRecordModalLabel"><i class="bi bi-plus-circle me-1" style="color: #2b5c8f;"></i>Add RSBSA Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/process_crud.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <div class="mb-3">
                        <label for="add-barangay" class="form-label small fw-semibold text-muted mb-1">Barangay</label>
                        <input type="text" name="barangay" id="add-barangay" class="form-control" required placeholder="e.g. San Jose">
                    </div>
                    <div class="mb-3">
                        <label for="add-crop" class="form-label small fw-semibold text-muted mb-1">Crop Type</label>
                        <select name="crop_type" id="add-crop" class="form-select" required>
                            <option value="Rice">Rice</option>
                            <option value="Corn">Corn</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-size" class="form-label small fw-semibold text-muted mb-1">Farm Size (sq.m)</label>
                        <input type="number" step="0.1" min="0.1" name="farm_size" id="add-size" class="form-control" required placeholder="e.g. 15000">
                    </div>
                    <div class="mb-3">
                        <label for="add-season" class="form-label small fw-semibold text-muted mb-1">Season</label>
                        <select name="season" id="add-season" class="form-select" required>
                            <option value="Wet Season">Wet Season</option>
                            <option value="Dry Season">Dry Season</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-intervention" class="form-label small fw-semibold text-muted mb-1">Intervention Received</label>
                        <input type="text" name="intervention_received" id="add-intervention" class="form-control" required placeholder="e.g. Fertilizer Seeds">
                    </div>
                    <div class="mb-3">
                        <label for="add-fertilizer" class="form-label small fw-semibold text-muted mb-1">Fertilizer Type</label>
                        <input type="text" name="fertilizer_type" id="add-fertilizer" class="form-control" required placeholder="e.g. Chemical">
                    </div>
                    <div class="mb-3">
                        <label for="add-application" class="form-label small fw-semibold text-muted mb-1">Application Type</label>
                        <input type="text" name="application_type" id="add-application" class="form-control" required placeholder="e.g. Pellet/Granular">
                    </div>
                </div>
                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: var(--radius-subtle); overflow: hidden;">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-dark" id="editRecordModalLabel"><i class="bi bi-pencil-square me-1" style="color: #bc6c25;"></i>Edit RSBSA Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/process_crud.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <div class="mb-3">
                        <label for="edit-barangay" class="form-label small fw-semibold text-muted mb-1">Barangay</label>
                        <input type="text" name="barangay" id="edit-barangay" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-crop" class="form-label small fw-semibold text-muted mb-1">Crop Type</label>
                        <select name="crop_type" id="edit-crop" class="form-select" required>
                            <option value="Rice">Rice</option>
                            <option value="Corn">Corn</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-size" class="form-label small fw-semibold text-muted mb-1">Farm Size (sq.m)</label>
                        <input type="number" step="0.1" min="0.1" name="farm_size" id="edit-size" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-season" class="form-label small fw-semibold text-muted mb-1">Season</label>
                        <select name="season" id="edit-season" class="form-select" required>
                            <option value="Wet Season">Wet Season</option>
                            <option value="Dry Season">Dry Season</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-intervention" class="form-label small fw-semibold text-muted mb-1">Intervention Received</label>
                        <input type="text" name="intervention_received" id="edit-intervention" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-fertilizer" class="form-label small fw-semibold text-muted mb-1">Fertilizer Type</label>
                        <input type="text" name="fertilizer_type" id="edit-fertilizer" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-application" class="form-label small fw-semibold text-muted mb-1">Application Type</label>
                        <input type="text" name="application_type" id="edit-application" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Drag & Drop and Upload handlers -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const fileInput = document.getElementById("csv_file");
    const uploadZone = document.getElementById("upload-zone");
    const fileInfo = document.getElementById("file-info");
    const selectedFileName = document.getElementById("selected-file-name");
    const submitBtn = document.getElementById("submit-btn");
    const clearFileBtn = document.getElementById("clear-file");

    fileInput.addEventListener("change", function () {
        if (fileInput.files.length > 0) {
            selectedFileName.textContent = fileInput.files[0].name;
            fileInfo.classList.remove("d-none");
            submitBtn.removeAttribute("disabled");
        } else {
            resetUploadForm();
        }
    });

    clearFileBtn.addEventListener("click", function () {
        resetUploadForm();
    });

    function resetUploadForm() {
        fileInput.value = "";
        selectedFileName.textContent = "";
        fileInfo.classList.add("d-none");
        submitBtn.setAttribute("disabled", "true");
    }

    ["dragenter", "dragover"].forEach(eventName => {
        uploadZone.addEventListener(eventName, function (e) {
            e.preventDefault();
            uploadZone.classList.add("dragover");
        }, false);
    });

    ["dragleave", "drop"].forEach(eventName => {
        uploadZone.addEventListener(eventName, function (e) {
            e.preventDefault();
            uploadZone.classList.remove("dragover");
        }, false);
    });
});
</script>

<!-- CRUD Handlers and Alerts Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Populate Edit Modal
    const editModal = document.getElementById('editRecordModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const barangay = button.getAttribute('data-barangay');
            const crop = button.getAttribute('data-crop');
            const size = button.getAttribute('data-size');
            const season = button.getAttribute('data-season');
            const intervention = button.getAttribute('data-intervention');
            const fertilizer = button.getAttribute('data-fertilizer');
            const application = button.getAttribute('data-application');

            editModal.querySelector('#edit-id').value = id;
            editModal.querySelector('#edit-barangay').value = barangay;
            editModal.querySelector('#edit-crop').value = crop;
            editModal.querySelector('#edit-size').value = size;
            editModal.querySelector('#edit-season').value = season;
            editModal.querySelector('#edit-intervention').value = intervention;
            editModal.querySelector('#edit-fertilizer').value = fertilizer;
            editModal.querySelector('#edit-application').value = application;
        });
    }

    // 2. Selection checkboxes logic for batch deletion
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const btnBatchDelete = document.getElementById('btn-batch-delete');
    const selectCountSpan = document.getElementById('select-count');

    function updateBatchDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const count = checkedBoxes.length;
        if (count > 0) {
            selectCountSpan.textContent = count;
            btnBatchDelete.classList.remove('d-none');
        } else {
            btnBatchDelete.classList.add('d-none');
        }
        
        if (checkAll && checkboxes.length > 0) {
            checkAll.checked = (count === checkboxes.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checkboxes.forEach(cb => {
                cb.checked = checkAll.checked;
            });
            updateBatchDeleteButton();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBatchDeleteButton);
    });
});

// 2. AJAX Delete Request with SweetAlert2 Confirmation
function deleteRecord(id) {
    Swal.fire({
        title: 'Delete Record?',
        text: "This will permanently remove RSBSA record ID " + id + ".",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
            popup: 'card border shadow-sm'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ""); ?>');

            fetch('actions/process_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        confirmButtonColor: '#1b5e20',
                        customClass: {
                            popup: 'card border shadow-sm'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#1b5e20',
                        customClass: {
                            popup: 'card border shadow-sm'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Failed to communicate with the server.',
                    confirmButtonColor: '#1b5e20',
                    customClass: {
                        popup: 'card border shadow-sm'
                    }
                });
            });
        }
    });
}

// 3. AJAX Batch Delete Request with SweetAlert2 Confirmation
function batchDeleteRecords() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkedBoxes).map(cb => cb.value);

    if (ids.length === 0) return;

    Swal.fire({
        title: 'Delete Selected?',
        text: "Are you sure you want to permanently remove " + ids.length + " selected record(s)?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete them!',
        customClass: {
            popup: 'card border shadow-sm'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'batch_delete');
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ""); ?>');
            ids.forEach(id => {
                formData.append('ids[]', id);
            });

            fetch('actions/process_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        confirmButtonColor: '#1b5e20',
                        customClass: {
                            popup: 'card border shadow-sm'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#1b5e20',
                        customClass: {
                            popup: 'card border shadow-sm'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Failed to communicate with the server.',
                    confirmButtonColor: '#1b5e20',
                    customClass: {
                        popup: 'card border shadow-sm'
                    }
                });
            });
        }
    });
}
</script>

<!-- SweetAlert2 Redirection Status Alert -->
<?php if (isset($_SESSION['upload_status'])): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        Swal.fire({
            icon: '<?php echo $_SESSION['upload_status']['icon']; ?>',
            title: '<?php echo htmlspecialchars($_SESSION['upload_status']['title']); ?>',
            text: '<?php echo htmlspecialchars($_SESSION['upload_status']['text']); ?>',
            confirmButtonColor: '#1b5e20',
            customClass: {
                popup: 'card border shadow-sm'
            }
        });
    });
    </script>
<?php unset($_SESSION['upload_status']); endif; ?>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
