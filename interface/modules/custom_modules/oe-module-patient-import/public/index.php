<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 5) . "/globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\PatientImport\Services\PatientImportService;

if (!AclMain::aclCheckCore('admin', 'practice') && !AclMain::aclCheckCore('patients', 'demo', '', 'write')) {
    echo xlt('Unauthorized access. You do not have permission to import patient data.');
    exit;
}

$csrfToken = CsrfUtils::collectCsrfToken(session: $session);
$mappableFields = PatientImportService::getMappableFields();

// Fetch previous import history
$historyLogs = [];
$res = sqlStatement("SELECT * FROM module_patient_import_logs ORDER BY id DESC LIMIT 10");
if ($res) {
    while ($row = sqlFetchArray($res)) {
        $historyLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo xlt("Patient Demographics Import"); ?></title>
    <?php Header::setupHeader(['common', 'fontawesome']); ?>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
        }

        body {
            background-color: var(--light-bg);
            color: #334155;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding-bottom: 50px;
        }

        .import-wrapper {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .page-header-card {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
            border-radius: 14px;
            color: #fff;
            padding: 24px 30px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header-title h2 {
            font-size: 1.55rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-title p {
            margin: 6px 0 0 0;
            color: #c7d2fe;
            font-size: 0.95rem;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-template {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.88rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-template:hover {
            background: rgba(255, 255, 255, 0.28);
            color: #fff;
            transform: translateY(-1px);
        }

        /* Step Wizard */
        .step-nav {
            display: flex;
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            padding: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .step-item {
            flex: 1;
            padding: 12px 18px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }

        .step-item.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .step-item.completed {
            color: var(--success);
        }

        .step-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
        }

        .step-item.active .step-number {
            background: var(--primary);
            color: #fff;
        }

        .step-item.completed .step-number {
            background: var(--success);
            color: #fff;
        }

        /* Main Card */
        .main-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            padding: 28px;
            margin-bottom: 28px;
        }

        /* Dropzone */
        .upload-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: var(--primary);
            background: #eef2ff;
        }

        .upload-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e0e7ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 16px;
        }

        .file-selected-box {
            display: none;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-top: 16px;
            align-items: center;
            justify-content: space-between;
        }

        /* Options card */
        .options-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-top: 24px;
        }

        .strategy-option {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .strategy-option:hover, .strategy-option.selected {
            border-color: var(--primary);
            background: #f5f7ff;
        }

        /* Mapping Table */
        .mapping-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 0.88rem;
            border-top: none;
        }

        .mapping-row {
            transition: background 0.15s ease;
        }

        .mapping-row:hover {
            background: #f8fafc;
        }

        .auto-matched-badge {
            font-size: 0.75rem;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* Results Stats */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-card.imported .stat-number { color: var(--success); }
        .stat-card.updated .stat-number { color: #3b82f6; }
        .stat-card.skipped .stat-number { color: var(--warning); }
        .stat-card.errors .stat-number { color: var(--danger); }
        .stat-card.total .stat-number { color: var(--primary); }

        /* History Table */
        .history-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 24px;
        }

        .badge-mode {
            background: #e2e8f0;
            color: #334155;
            font-size: 0.78rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="import-wrapper">

    <!-- Header Card -->
    <div class="page-header-card">
        <div class="page-header-title">
            <h2><i class="fa fa-users-cog"></i> <?php echo xlt("Patient Demographics Import"); ?></h2>
            <p><?php echo xlt("Import and map patient records from CSV or Microsoft Excel spreadsheets into OpenEMR."); ?></p>
        </div>
        <div class="header-actions">
            <a href="template_download.php?format=csv" class="btn-template">
                <i class="fa fa-file-csv"></i> <?php echo xlt("Download Sample CSV"); ?>
            </a>
            <a href="template_download.php?format=xlsx" class="btn-template">
                <i class="fa fa-file-excel"></i> <?php echo xlt("Download Sample Excel"); ?>
            </a>
        </div>
    </div>

    <!-- Step Progress Bar -->
    <div class="step-nav">
        <div class="step-item active" id="step-nav-1">
            <div class="step-number">1</div>
            <div><?php echo xlt("1. Select & Upload File"); ?></div>
        </div>
        <div class="step-item" id="step-nav-2">
            <div class="step-number">2</div>
            <div><?php echo xlt("2. Map Columns & Preview"); ?></div>
        </div>
        <div class="step-item" id="step-nav-3">
            <div class="step-number">3</div>
            <div><?php echo xlt("3. Import Results"); ?></div>
        </div>
    </div>

    <!-- STEP 1: Upload & Options -->
    <div class="main-card" id="step-1-card">
        <h4 class="font-weight-bold mb-3"><?php echo xlt("Step 1: Upload Demographic Data File"); ?></h4>
        <p class="text-muted mb-4"><?php echo xlt("Select a CSV (.csv) or Excel (.xlsx, .xls) file containing patient demographic details."); ?></p>

        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo attr($csrfToken); ?>">
            <div class="upload-dropzone" id="dropzone" style="position: relative; cursor: pointer; overflow: hidden;">
                <input type="file" id="fileInput" name="file" accept=".csv, .xlsx, .xls, text/csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" onchange="window.handleFileSelected ? window.handleFileSelected() : null" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                <div class="upload-icon">
                    <i class="fa fa-cloud-upload-alt"></i>
                </div>
                <h5 class="font-weight-bold mb-1"><?php echo xlt("Click to browse or drag and drop your spreadsheet here"); ?></h5>
                <p class="text-muted small mb-3"><?php echo xlt("Supported formats: .CSV, .XLSX, .XLS (Up to 25MB)"); ?></p>
                <span class="btn btn-outline-primary btn-sm px-3" style="pointer-events: none;">
                    <i class="fa fa-folder-open mr-1"></i> <?php echo xlt("Browse File"); ?>
                </span>
            </div>

            <div class="file-selected-box" id="fileSelectedBox">
                <div class="d-flex align-items-center gap-3">
                    <i class="fa fa-file-excel text-success fa-2x mr-2"></i>
                    <div>
                        <strong id="selectedFileName">file.xlsx</strong>
                        <div class="text-muted small" id="selectedFileSize">0 KB</div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnRemoveFile">
                    <i class="fa fa-times mr-1"></i> <?php echo xlt("Change File"); ?>
                </button>
            </div>

            <!-- Duplicate Handling Configuration -->
            <div class="options-box">
                <h6 class="font-weight-bold mb-3 text-dark">
                    <i class="fa fa-clone text-primary mr-1"></i> <?php echo xlt("Duplicate Record Handling"); ?>
                </h6>

                <label class="strategy-option selected">
                    <input type="radio" name="duplicate_strategy" value="skip" checked class="mt-1">
                    <div>
                        <strong><?php echo xlt("Skip Duplicates (Recommended)"); ?></strong>
                        <div class="text-muted small"><?php echo xlt("If a patient already exists with matching SSN or First Name + Last Name + Date of Birth, skip the row and log it."); ?></div>
                    </div>
                </label>

                <label class="strategy-option">
                    <input type="radio" name="duplicate_strategy" value="update" class="mt-1">
                    <div>
                        <strong><?php echo xlt("Update Existing Patient Records"); ?></strong>
                        <div class="text-muted small"><?php echo xlt("If a matching patient is found, update their demographic details with non-empty fields from the spreadsheet."); ?></div>
                    </div>
                </label>

                <label class="strategy-option">
                    <input type="radio" name="duplicate_strategy" value="create" class="mt-1">
                    <div>
                        <strong><?php echo xlt("Always Create New Patient (Allow Duplicates)"); ?></strong>
                        <div class="text-muted small"><?php echo xlt("Always insert each row as a brand new patient record regardless of existing records."); ?></div>
                    </div>
                </label>
            </div>

            <div class="text-right mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-4" id="btnUpload" style="background: var(--primary); border: none; cursor: pointer;">
                    <?php echo xlt("Proceed to Column Mapping"); ?> <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- STEP 2: Column Mapping & Preview -->
    <div class="main-card" id="step-2-card" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="font-weight-bold mb-1"><?php echo xlt("Step 2: Map Columns & Preview Data"); ?></h4>
                <p class="text-muted mb-0">
                    <?php echo xlt("We have automatically matched columns based on your headers. Verify or adjust the mappings below."); ?>
                </p>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackToStep1">
                <i class="fa fa-arrow-left mr-1"></i> <?php echo xlt("Choose Another File"); ?>
            </button>
        </div>

        <div class="alert alert-info py-2 px-3 d-flex align-items-center justify-content-between mb-4">
            <div>
                <i class="fa fa-info-circle mr-1"></i>
                <span id="detectedRowsCount">0</span> <?php echo xlt("data rows detected in"); ?> <strong id="detectedFilename">file.csv</strong>.
            </div>
            <small class="font-weight-bold text-primary"><?php echo xlt("Required fields: First Name & Last Name"); ?></small>
        </div>

        <!-- Column Mapping Section -->
        <div class="table-responsive mb-4 border rounded">
            <table class="table table-hover mapping-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php echo xlt("File Column Header"); ?></th>
                        <th style="width: 35%;"><?php echo xlt("Sample Value (Row 1)"); ?></th>
                        <th style="width: 35%;"><?php echo xlt("Target OpenEMR Field"); ?></th>
                    </tr>
                </thead>
                <tbody id="mappingTbody">
                    <!-- Populated via JS -->
                </tbody>
            </table>
        </div>

        <!-- Live Preview of first 5 rows -->
        <h6 class="font-weight-bold mb-2 text-dark">
            <i class="fa fa-table text-primary mr-1"></i> <?php echo xlt("Live Data Preview (First 5 Rows)"); ?>
        </h6>
        <div class="table-responsive border rounded mb-4" style="max-height: 280px; overflow-y: auto;">
            <table class="table table-sm table-striped table-bordered mb-0" id="previewTable" style="font-size: 0.85rem;">
                <!-- Populated via JS -->
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <button type="button" class="btn btn-outline-secondary px-3" id="btnBackToStep1Bottom">
                <i class="fa fa-arrow-left mr-1"></i> <?php echo xlt("Back"); ?>
            </button>
            <button type="button" class="btn btn-success btn-lg px-4" id="btnExecuteImport" style="background: var(--success); border: none;">
                <i class="fa fa-check-circle mr-2"></i> <?php echo xlt("Execute Import"); ?>
            </button>
        </div>
    </div>

    <!-- STEP 3: Results -->
    <div class="main-card" id="step-3-card" style="display: none;">
        <div class="text-center py-4" id="importLoadingSpinner" style="display: none;">
            <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                <span class="sr-only"><?php echo xlt("Importing..."); ?></span>
            </div>
            <h4 class="font-weight-bold text-dark mb-1"><?php echo xlt("Importing Patient Records..."); ?></h4>
            <p class="text-muted"><?php echo xlt("Please wait while patient demographic records are processed and validated."); ?></p>
        </div>

        <div id="importResultsContainer" style="display: none;">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle bg-light text-success mb-2" style="font-size: 2rem;">
                    <i class="fa fa-check-double"></i>
                </div>
                <h3 class="font-weight-bold text-dark"><?php echo xlt("Import Complete"); ?></h3>
                <p class="text-muted"><?php echo xlt("The patient demographics import batch has completed processing."); ?></p>
            </div>

            <!-- Stats Grid -->
            <div class="stat-grid">
                <div class="stat-card total">
                    <div class="stat-number" id="resTotal">0</div>
                    <div class="stat-label"><?php echo xlt("Total Rows"); ?></div>
                </div>
                <div class="stat-card imported">
                    <div class="stat-number" id="resImported">0</div>
                    <div class="stat-label"><?php echo xlt("New Patients"); ?></div>
                </div>
                <div class="stat-card updated">
                    <div class="stat-number" id="resUpdated">0</div>
                    <div class="stat-label"><?php echo xlt("Updated"); ?></div>
                </div>
                <div class="stat-card skipped">
                    <div class="stat-number" id="resSkipped">0</div>
                    <div class="stat-label"><?php echo xlt("Skipped"); ?></div>
                </div>
                <div class="stat-card errors">
                    <div class="stat-number" id="resErrors">0</div>
                    <div class="stat-label"><?php echo xlt("Errors"); ?></div>
                </div>
            </div>

            <!-- Error / Warning Details Table -->
            <div id="errorDetailsSection" style="display: none;" class="mt-4">
                <h5 class="font-weight-bold text-dark mb-2">
                    <i class="fa fa-exclamation-triangle text-warning mr-1"></i> <?php echo xlt("Skipped & Error Logs"); ?>
                </h5>
                <div class="table-responsive border rounded mb-4" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 10%;"><?php echo xlt("Row #"); ?></th>
                                <th style="width: 25%;"><?php echo xlt("Patient Name"); ?></th>
                                <th style="width: 15%;"><?php echo xlt("Status"); ?></th>
                                <th style="width: 50%;"><?php echo xlt("Details / Reason"); ?></th>
                            </tr>
                        </thead>
                        <tbody id="errorDetailsTbody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary" id="btnImportAnother">
                    <i class="fa fa-redo mr-1"></i> <?php echo xlt("Import Another File"); ?>
                </button>
                <a href="<?php echo attr(OEGlobalsBag::getInstance()->getWebRoot() . '/interface/patient_file/summary/demographics.php'); ?>" class="btn btn-primary" target="_top">
                    <i class="fa fa-search mr-1"></i> <?php echo xlt("Open Demographics Search"); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- History Card -->
    <div class="history-card">
        <h5 class="font-weight-bold mb-3 text-dark">
            <i class="fa fa-history text-muted mr-2"></i> <?php echo xlt("Recent Import Batches"); ?>
        </h5>
        <?php if (!empty($historyLogs)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size: 0.88rem;">
                    <thead class="thead-light">
                        <tr>
                            <th><?php echo xlt("Date & Time"); ?></th>
                            <th><?php echo xlt("File Name"); ?></th>
                            <th><?php echo xlt("Strategy"); ?></th>
                            <th class="text-center"><?php echo xlt("Total"); ?></th>
                            <th class="text-center"><?php echo xlt("Imported"); ?></th>
                            <th class="text-center"><?php echo xlt("Updated"); ?></th>
                            <th class="text-center"><?php echo xlt("Skipped"); ?></th>
                            <th class="text-center"><?php echo xlt("Errors"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyLogs as $log): ?>
                            <tr>
                                <td><?php echo text($log['created_at']); ?></td>
                                <td><strong><?php echo text($log['filename']); ?></strong></td>
                                <td><span class="badge-mode"><?php echo text($log['duplicate_mode']); ?></span></td>
                                <td class="text-center font-weight-bold"><?php echo text($log['total_rows']); ?></td>
                                <td class="text-center text-success font-weight-bold"><?php echo text($log['imported_count']); ?></td>
                                <td class="text-center text-primary"><?php echo text($log['updated_count']); ?></td>
                                <td class="text-center text-warning"><?php echo text($log['skipped_count']); ?></td>
                                <td class="text-center text-danger"><?php echo text($log['error_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted py-3 text-center">
                <i class="fa fa-info-circle mr-1"></i> <?php echo xlt("No previous import records found."); ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
$(document).ready(function () {
    const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
    const MAPPABLE_FIELDS = <?php echo json_encode($mappableFields); ?>;
    let parsedData = null;

    // Radio button UI toggle
    $('input[name="duplicate_strategy"]').change(function () {
        $('.strategy-option').removeClass('selected');
        $(this).closest('.strategy-option').addClass('selected');
    });

    // Dropzone interaction
    const dropzone = $('#dropzone');
    const fileInput = $('#fileInput');

    fileInput.on('dragenter dragover', function () {
        dropzone.addClass('dragover');
    });

    fileInput.on('dragleave dragend drop', function () {
        dropzone.removeClass('dragover');
    });

    dropzone.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.removeClass('dragover');
        const dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length) {
            const fileInputEl = document.getElementById('fileInput');
            fileInputEl.files = dt.files;
            handleFileSelected();
        }
    });

    window.handleFileSelected = function () {
        const fileInputEl = document.getElementById('fileInput');
        const file = (fileInputEl && fileInputEl.files && fileInputEl.files.length > 0) ? fileInputEl.files[0] : null;
        if (!file) {
            return;
        }

        $('#selectedFileName').text(file.name);
        $('#selectedFileSize').text((file.size / 1024).toFixed(1) + ' KB');
        $('#dropzone').hide();
        $('#fileSelectedBox').css('display', 'flex').show();
        $('#btnUpload').prop('disabled', false).removeAttr('disabled').removeClass('disabled');
    };

    fileInput.on('change', function () {
        window.handleFileSelected();
    });

    $('#btnRemoveFile').on('click', function () {
        const fileInputEl = document.getElementById('fileInput');
        if (fileInputEl) {
            fileInputEl.value = '';
        }
        fileInput.val('');
        $('#selectedFileName').text('');
        $('#selectedFileSize').text('');
        $('#fileSelectedBox').hide();
        $('#dropzone').show();
    });

    // Handle Upload & Parse
    $('#uploadForm').on('submit', function (e) {
        e.preventDefault();
        const fileInputEl = document.getElementById('fileInput');
        const file = (fileInputEl && fileInputEl.files && fileInputEl.files.length > 0) ? fileInputEl.files[0] : null;
        if (!file) {
            alert('Please select a CSV (.csv) or Excel (.xlsx, .xls) file first.');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'parse');
        formData.append('file', file);

        $('#btnUpload').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i> Analyzing File...');

        $.ajax({
            url: 'ajax_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                $('#btnUpload').prop('disabled', false).html('Proceed to Column Mapping <i class="fa fa-arrow-right ml-2"></i>');
                if (res && res.success) {
                    parsedData = res;
                    renderStep2(res);
                } else {
                    alert((res && res.message) ? res.message : 'Failed to parse file.');
                }
            },
            error: function (xhr) {
                $('#btnUpload').prop('disabled', false).html('Proceed to Column Mapping <i class="fa fa-arrow-right ml-2"></i>');
                let err = 'Server error occurred during file parsing';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    err += ': ' + xhr.responseJSON.message;
                } else if (xhr.statusText) {
                    err += ': ' + xhr.statusText;
                }
                alert(err);
            }
        });
    });

    function renderStep2(data) {
        $('#detectedFilename').text(data.filename);
        $('#detectedRowsCount').text(data.total_rows);

        // Build mapping rows
        const tbody = $('#mappingTbody');
        tbody.empty();

        data.headers.forEach((header, idx) => {
            const sampleVal = (data.preview_rows.length > 0 && data.preview_rows[0][idx] !== undefined) ? data.preview_rows[0][idx] : '-';
            const autoMatchKey = data.auto_mapping[idx] || '';

            let selectHtml = `<select class="form-control form-control-sm column-map-select" data-col-idx="${idx}">`;
            selectHtml += `<option value="">-- Do Not Import --</option>`;

            for (const [key, field] of Object.entries(MAPPABLE_FIELDS)) {
                const isSelected = (autoMatchKey === key) ? 'selected' : '';
                const reqStar = field.required ? ' *' : '';
                selectHtml += `<option value="${key}" ${isSelected}>${field.label}${reqStar}</option>`;
            }
            selectHtml += `</select>`;

            const badgeHtml = autoMatchKey ? `<span class="auto-matched-badge ml-2"><i class="fa fa-magic mr-1"></i>Auto-matched</span>` : '';

            const tr = `
                <tr class="mapping-row">
                    <td class="align-middle font-weight-bold">${escapeHtml(header)} ${badgeHtml}</td>
                    <td class="align-middle text-muted font-italic">${escapeHtml(sampleVal)}</td>
                    <td class="align-middle">${selectHtml}</td>
                </tr>
            `;
            tbody.append(tr);
        });

        // Render preview table
        renderPreviewTable(data);

        // Switch cards
        $('#step-1-card').hide();
        $('#step-2-card').show();
        $('#step-nav-1').removeClass('active').addClass('completed');
        $('#step-nav-2').addClass('active');
    }

    function renderPreviewTable(data) {
        const previewTable = $('#previewTable');
        previewTable.empty();

        let thead = '<thead class="thead-light"><tr>';
        data.headers.forEach(h => {
            thead += `<th>${escapeHtml(h)}</th>`;
        });
        thead += '</tr></thead>';

        let tbody = '<tbody>';
        data.preview_rows.forEach(row => {
            tbody += '<tr>';
            data.headers.forEach((h, idx) => {
                const cell = row[idx] !== undefined ? row[idx] : '';
                tbody += `<td>${escapeHtml(cell)}</td>`;
            });
            tbody += '</tr>';
        });
        tbody += '</tbody>';

        previewTable.html(thead + tbody);
    }

    // Step navigation buttons
    $('#btnBackToStep1, #btnBackToStep1Bottom').on('click', function () {
        $('#step-2-card').hide();
        $('#step-1-card').show();
        $('#step-nav-2').removeClass('active');
        $('#step-nav-1').removeClass('completed').addClass('active');
    });

    // Execute Import
    $('#btnExecuteImport').on('click', function () {
        if (!parsedData || !parsedData.temp_key) return;

        // Collect mapping
        const mapping = {};
        let hasNameMapped = false;

        $('.column-map-select').each(function () {
            const colIdx = $(this).data('col-idx');
            const targetField = $(this).val();
            if (targetField) {
                mapping[colIdx] = targetField;
                if (targetField === 'fname' || targetField === 'lname') {
                    hasNameMapped = true;
                }
            }
        });

        if (!hasNameMapped) {
            alert('Please map at least First Name or Last Name to import patient records.');
            return;
        }

        const duplicateStrategy = $('input[name="duplicate_strategy"]:checked').val() || 'skip';

        // Switch to Step 3 Loading
        $('#step-2-card').hide();
        $('#step-3-card').show();
        $('#step-nav-2').removeClass('active').addClass('completed');
        $('#step-nav-3').addClass('active');
        $('#importLoadingSpinner').show();
        $('#importResultsContainer').hide();

        $.ajax({
            url: 'ajax_import.php',
            type: 'POST',
            data: {
                csrf_token: CSRF_TOKEN,
                action: 'import',
                temp_key: parsedData.temp_key,
                mapping: mapping,
                duplicate_strategy: duplicateStrategy
            },
            dataType: 'json',
            success: function (res) {
                $('#importLoadingSpinner').hide();
                if (res.success) {
                    renderStep3Results(res);
                } else {
                    alert(res.message || 'Import failed.');
                    $('#step-3-card').hide();
                    $('#step-2-card').show();
                }
            },
            error: function (xhr) {
                $('#importLoadingSpinner').hide();
                alert('Server error occurred during import: ' + xhr.statusText);
                $('#step-3-card').hide();
                $('#step-2-card').show();
            }
        });
    });

    function renderStep3Results(res) {
        $('#importResultsContainer').show();
        $('#resTotal').text(res.total_rows);
        $('#resImported').text(res.imported);
        $('#resUpdated').text(res.updated);
        $('#resSkipped').text(res.skipped);
        $('#resErrors').text(res.errors);

        if (res.error_details && res.error_details.length > 0) {
            $('#errorDetailsSection').show();
            const tbody = $('#errorDetailsTbody');
            tbody.empty();

            res.error_details.forEach(item => {
                const badgeClass = (item.status === 'Updated') ? 'badge-primary' : (item.status === 'Skipped' ? 'badge-warning' : 'badge-danger');
                const tr = `
                    <tr>
                        <td class="font-weight-bold">Row ${escapeHtml(item.row)}</td>
                        <td>${escapeHtml(item.name || '-')}</td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(item.status)}</span></td>
                        <td class="text-muted">${escapeHtml(item.reason || '')}</td>
                    </tr>
                `;
                tbody.append(tr);
            });
        } else {
            $('#errorDetailsSection').hide();
        }
    }

    $('#btnImportAnother').on('click', function () {
        parsedData = null;
        fileInput.val('');
        $('#fileSelectedBox').hide();
        dropzone.show();
        $('#btnUpload').prop('disabled', true);

        $('#step-3-card').hide();
        $('#step-1-card').show();
        $('#step-nav-3').removeClass('active');
        $('#step-nav-2').removeClass('completed active');
        $('#step-nav-1').removeClass('completed').addClass('active');

        // Reload page to refresh history table
        window.location.reload();
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>
</body>
</html>
