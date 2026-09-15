<?php

/**
 * index.php
 *
 * Admin management page for Group Header Canvas & Annotation module
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasAdminController;

$controller = new GroupCanvasAdminController();
if (!$controller->checkAuth()) {
    AccessDeniedHelper::denyWithTemplate("ACL check failed for Group Canvas Admin", xl("Group Canvas Settings"));
}

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$csrfToken = CsrfUtils::collectCsrfToken(session: $session);
$formsWithGroups = $controller->getLayoutForms();

$selectedFormId = $_GET['form_id'] ?? (array_key_first($formsWithGroups) ?? '');
if (!isset($formsWithGroups[$selectedFormId]) && !empty($formsWithGroups)) {
    $selectedFormId = array_key_first($formsWithGroups);
}

$currentForm = $formsWithGroups[$selectedFormId] ?? null;
$webroot = OEGlobalsBag::getInstance()->getWebRoot();
$uploadUrl = $webroot . '/interface/modules/custom_modules/oe-module-group-canvas/public/uploads/';
$siteImagesUrl = $webroot . '/sites/' . $session->get('site_id') . '/images/';
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['opener', 'common']); ?>
    <title><?php echo xlt("Group Canvas & Annotation Settings"); ?></title>
    <style>
        .card-header-canvas {
            background-color: var(--primary);
            color: var(--white);
            font-weight: 600;
        }
        .group-card {
            border: 1px solid var(--gray300);
            border-radius: 6px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .group-card:hover {
            box-shadow: 0 4px 10px rgba(0,0,0,0.09);
        }
        .image-preview-box {
            max-width: 100%;
            height: 160px;
            border: 2px dashed var(--gray400);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light);
            overflow: hidden;
            position: relative;
        }
        .image-preview-box img {
            max-height: 150px;
            max-width: 100%;
            object-fit: contain;
        }
        .custom-switch .custom-control-label {
            font-weight: 600;
            cursor: pointer;
        }
        .status-badge-active {
            background-color: var(--success);
            color: #fff;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .status-badge-inactive {
            background-color: var(--secondary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="body_top p-3 bg-light">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center bg-white p-3 border rounded shadow-sm">
                <div>
                    <h3 class="m-0 text-primary font-weight-bold">
                        <i class="fa fa-paint-brush mr-2"></i><?php echo xlt("Group Header Canvas & Annotation Settings"); ?>
                    </h3>
                    <small class="text-muted"><?php echo xlt("Configure interactive background diagrams and canvas drawing for each layout group header."); ?></small>
                </div>
                <div>
                    <button class="btn btn-secondary btn-sm" onclick="location.reload();">
                        <i class="fa fa-sync-alt mr-1"></i><?php echo xlt("Refresh"); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Sidebar: Layout Selector -->
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white font-weight-bold">
                        <i class="fa fa-list mr-2"></i><?php echo xlt("Select Layout Form"); ?>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 75vh; overflow-y: auto;">
                        <?php foreach ($formsWithGroups as $fId => $fData) { ?>
                            <a href="?form_id=<?php echo attr_url($fId); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $selectedFormId === $fId ? 'active font-weight-bold' : ''; ?>">
                                <span><?php echo text($fData['form_title']); ?></span>
                                <span class="badge badge-pill <?php echo $selectedFormId === $fId ? 'badge-light text-dark' : 'badge-secondary'; ?>">
                                    <?php echo count($fData['groups']); ?> <?php echo xlt("groups"); ?>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Main Content: Groups for Selected Layout -->
            <div class="col-md-9">
                <?php if ($currentForm) { ?>
                    <div class="bg-white p-3 border rounded shadow-sm mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="m-0 text-dark">
                                <?php echo xlt("Layout"); ?>: <strong><?php echo text($currentForm['form_title']); ?></strong>
                                <span class="badge badge-info ml-2 font-weight-normal"><?php echo text($selectedFormId); ?></span>
                            </h4>
                            <p class="text-muted small m-0 mt-1"><?php echo xlt("Enable or disable canvas annotations and upload specific background diagrams for each group below."); ?></p>
                        </div>
                    </div>

                    <?php if (empty($currentForm['groups'])) { ?>
                        <div class="alert alert-warning"><?php echo xlt("No groups found in this layout form."); ?></div>
                    <?php } ?>

                    <?php foreach ($currentForm['groups'] as $index => $group) { 
                        $cfg = $group['config'] ?? null;
                        $isEnabled = $cfg && !empty($cfg['is_enabled']);
                        $btnLabel = $cfg['button_label'] ?? 'Annotate Diagram';
                        $bgImage = $cfg['background_image'] ?? '';
                        $cWidth = $cfg['canvas_width'] ?? 800;
                        $cHeight = $cfg['canvas_height'] ?? 600;

                        $imgSrc = '';
                        if (!empty($bgImage)) {
                            $uploadPath = dirname(__DIR__, 2) . '/public/uploads/' . $bgImage;
                            if (file_exists($uploadPath)) {
                                $imgSrc = $uploadUrl . $bgImage;
                            } else {
                                $imgSrc = $siteImagesUrl . $bgImage;
                            }
                        }
                    ?>
                        <div class="card group-card" id="card_<?php echo attr($group['group_id']); ?>">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                                <div>
                                    <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                                        <i class="fa fa-folder-open text-primary mr-2"></i><?php echo text($group['group_title']); ?>
                                    </span>
                                    <small class="text-muted ml-2">(Group ID: <code><?php echo text($group['group_id']); ?></code>)</small>
                                </div>
                                <div>
                                    <span id="badge_<?php echo attr($group['group_id']); ?>" class="<?php echo $isEnabled ? 'status-badge-active' : 'status-badge-inactive'; ?>">
                                        <?php echo $isEnabled ? xlt("Canvas Enabled") : xlt("Disabled"); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <form class="group-canvas-form" id="form_<?php echo attr($group['group_id']); ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
                                    <input type="hidden" name="form_id" value="<?php echo attr($selectedFormId); ?>">
                                    <input type="hidden" name="group_id" value="<?php echo attr($group['group_id']); ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo attr($bgImage); ?>">

                                    <div class="row">
                                        <!-- Configuration Column -->
                                        <div class="col-md-7">
                                            <div class="form-group custom-control custom-switch mb-3">
                                                <input type="checkbox" class="custom-control-input canvas-enable-switch" 
                                                       id="switch_<?php echo attr($group['group_id']); ?>" 
                                                       name="is_enabled" value="1" <?php echo $isEnabled ? 'checked' : ''; ?>>
                                                <label class="custom-control-label font-weight-bold text-dark" for="switch_<?php echo attr($group['group_id']); ?>">
                                                    <?php echo xlt("Enable Canvas Button on this Group Header"); ?>
                                                </label>
                                            </div>

                                            <div class="form-group">
                                                <label class="font-weight-bold small text-muted text-uppercase"><?php echo xlt("Button Label on Group Header"); ?>:</label>
                                                <input type="text" class="form-control form-control-sm" name="button_label" value="<?php echo attr($btnLabel); ?>" placeholder="e.g. Annotate Musculoskeletal Diagram">
                                            </div>

                                            <div class="form-row">
                                                <div class="col-6 form-group">
                                                    <label class="font-weight-bold small text-muted text-uppercase"><?php echo xlt("Canvas Width (px)"); ?>:</label>
                                                    <input type="number" class="form-control form-control-sm" name="canvas_width" value="<?php echo attr($cWidth); ?>" min="300" max="1920">
                                                </div>
                                                <div class="col-6 form-group">
                                                    <label class="font-weight-bold small text-muted text-uppercase"><?php echo xlt("Canvas Height (px)"); ?>:</label>
                                                    <input type="number" class="form-control form-control-sm" name="canvas_height" value="<?php echo attr($cHeight); ?>" min="200" max="1200">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="font-weight-bold small text-muted text-uppercase"><?php echo xlt("Upload Background Image Template"); ?>:</label>
                                                <input type="file" class="form-control-file bg-light p-2 border rounded image-file-input" 
                                                       name="background_image" accept="image/*" data-group="<?php echo attr($group['group_id']); ?>">
                                                <small class="form-text text-muted"><?php echo xlt("Upload anatomical diagram (PNG, JPG, SVG). It will appear as the template for drawing."); ?></small>
                                            </div>
                                        </div>

                                        <!-- Image Preview Column -->
                                        <div class="col-md-5 d-flex flex-column justify-content-center align-items-center">
                                            <div class="w-100 mb-2">
                                                <small class="font-weight-bold text-muted text-uppercase"><?php echo xlt("Template Preview"); ?>:</small>
                                            </div>
                                            <div class="image-preview-box w-100" id="preview_box_<?php echo attr($group['group_id']); ?>">
                                                <?php if (!empty($imgSrc)) { ?>
                                                    <img src="<?php echo attr($imgSrc); ?>" id="preview_img_<?php echo attr($group['group_id']); ?>" alt="Template">
                                                    <span class="text-muted small" id="preview_placeholder_<?php echo attr($group['group_id']); ?>" style="display:none;">
                                                        <i class="fa fa-image fa-2x d-block mb-1 text-center"></i><?php echo xlt("No background image uploaded"); ?>
                                                    </span>
                                                <?php } else { ?>
                                                    <span class="text-muted small" id="preview_placeholder_<?php echo attr($group['group_id']); ?>">
                                                        <i class="fa fa-image fa-2x d-block mb-1 text-center"></i><?php echo xlt("No background image uploaded"); ?>
                                                    </span>
                                                    <img src="" id="preview_img_<?php echo attr($group['group_id']); ?>" style="display:none;" alt="Template">
                                                <?php } ?>
                                            </div>
                                            <div class="mt-2 text-center" id="remove_wrapper_<?php echo attr($group['group_id']); ?>" style="<?php echo empty($imgSrc) ? 'display:none;' : ''; ?>">
                                                <button type="button" class="btn btn-danger btn-sm remove-image-btn" data-group="<?php echo attr($group['group_id']); ?>" data-form="<?php echo attr($selectedFormId); ?>">
                                                    <i class="fa fa-trash-alt mr-1"></i><?php echo xlt("Remove Image"); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div id="msg_<?php echo attr($group['group_id']); ?>"></div>
                                        <button type="button" class="btn btn-primary btn-sm save-group-btn" data-group="<?php echo attr($group['group_id']); ?>">
                                            <i class="fa fa-save mr-1"></i><?php echo xlt("Save Group Settings"); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>

                <?php } else { ?>
                    <div class="alert alert-info">
                        <?php echo xlt("Please select a layout form from the sidebar."); ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            // Live file preview
            $('.image-file-input').on('change', function () {
                const groupId = $(this).data('group');
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        $('#preview_img_' + groupId).attr('src', e.target.result).show();
                        $('#preview_placeholder_' + groupId).hide();
                        $('#remove_wrapper_' + groupId).show();
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Toggle switch badge update
            $('.canvas-enable-switch').on('change', function () {
                const groupId = $(this).attr('id').replace('switch_', '');
                const isChecked = $(this).is(':checked');
                const badge = $('#badge_' + groupId);
                if (isChecked) {
                    badge.removeClass('status-badge-inactive').addClass('status-badge-active').text(<?php echo xlj('Canvas Enabled'); ?>);
                } else {
                    badge.removeClass('status-badge-active').addClass('status-badge-inactive').text(<?php echo xlj('Disabled'); ?>);
                }
            });

            // Save group configuration via AJAX
            $('.save-group-btn').on('click', function () {
                const groupId = $(this).data('group');
                const form = document.getElementById('form_' + groupId);
                const formData = new FormData(form);
                const btn = $(this);
                const msgBox = $('#msg_' + groupId);

                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> ' + <?php echo xlj('Saving...'); ?>);
                msgBox.html('');

                $.ajax({
                    url: 'save_config.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> ' + <?php echo xlj('Save Group Settings'); ?>);
                        if (res.success) {
                            msgBox.html('<span class="text-success font-weight-bold"><i class="fa fa-check-circle mr-1"></i> ' + res.message + '</span>');
                            if (res.image) {
                                $(form).find('input[name="existing_image"]').val(res.image);
                                $('#remove_wrapper_' + groupId).show();
                            }
                            setTimeout(() => { msgBox.fadeOut(); }, 3500);
                        } else {
                            msgBox.html('<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle mr-1"></i> ' + res.message + '</span>');
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> ' + <?php echo xlj('Save Group Settings'); ?>);
                        msgBox.html('<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle mr-1"></i> ' + <?php echo xlj('Request failed. Please try again.'); ?> + '</span>');
                    }
                });
            });

            // Remove group image via AJAX
            $('.remove-image-btn').on('click', function () {
                const groupId = $(this).data('group');
                const formId = $(this).data('form');
                if (!confirm(<?php echo xlj('Are you sure you want to remove the template image for this group?'); ?>)) {
                    return;
                }
                const btn = $(this);
                const msgBox = $('#msg_' + groupId);
                btn.prop('disabled', true);

                $.ajax({
                    url: '../api/delete_config.php',
                    type: 'POST',
                    data: {
                        form_id: formId,
                        group_id: groupId,
                        csrf_token_form: <?php echo json_encode($csrfToken); ?>
                    },
                    dataType: 'json',
                    success: function (res) {
                        btn.prop('disabled', false);
                        if (res.success) {
                            $('#preview_img_' + groupId).attr('src', '').hide();
                            $('#preview_placeholder_' + groupId).show();
                            $('#form_' + groupId).find('input[name="existing_image"]').val('');
                            $('#form_' + groupId).find('.image-file-input').val('');
                            $('#remove_wrapper_' + groupId).hide();
                            msgBox.html('<span class="text-success font-weight-bold"><i class="fa fa-check-circle mr-1"></i> ' + res.message + '</span>');
                            setTimeout(() => { msgBox.fadeOut(); }, 3500);
                        } else {
                            msgBox.html('<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle mr-1"></i> ' + res.message + '</span>');
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false);
                        msgBox.html('<span class="text-danger font-weight-bold"><i class="fa fa-exclamation-circle mr-1"></i> ' + <?php echo xlj('Failed to remove image.'); ?> + '</span>');
                    }
                });
            });
        });
    </script>
</body>
</html>
