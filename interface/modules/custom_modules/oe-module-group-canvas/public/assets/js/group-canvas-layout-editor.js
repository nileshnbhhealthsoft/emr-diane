/**
 * group-canvas-layout-editor.js
 *
 * Injects Group Canvas configuration buttons and "Encounter Summary Hide" checkboxes
 * into OpenEMR Layout Editor (edit_layout.php) for clinical forms (LBF layouts).
 *
 * @package OpenEMR
 * @author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 */

(function ($) {
    'use strict';

    var LayoutCanvasEditor = {
        formId: '',
        csrfToken: '',
        moduleBaseUrl: '',
        configs: {},
        hiddenFields: {},
        activeGroupId: '',
        activeGroupTitle: '',

        init: function () {
            var self = this;
            $(document).ready(function () {
                self.detectContext();
                if (self.isClinicalForm()) {
                    self.loadConfigs(function () {
                        self.injectToolbarButtons();
                        self.injectEncounterSummaryHideColumns();
                        self.ensureModalDOM();
                        self.bindHiddenFieldEvents();
                    });
                }
            });
        },

        detectContext: function () {
            var urlParams = new URLSearchParams(window.location.search);
            this.formId = $('#layout_id').val() || $('select[name="layout_id"]').val() || urlParams.get('layout_id') || '';

            // Get CSRF Token
            this.csrfToken = $('input[name="csrf_token_form"]').val() ||
                             (typeof csrf_token_form !== 'undefined' ? csrf_token_form : '');

            // Base URL of module
            var webroot = (typeof top.webroot !== 'undefined' ? top.webroot : '') || '';
            if (!webroot) {
                var match = window.location.pathname.match(/^(\/[^\/]+)/);
                webroot = match ? match[1] : '';
            }
            this.moduleBaseUrl = webroot + '/interface/modules/custom_modules/oe-module-group-canvas';
        },

        isClinicalForm: function () {
            // Group canvas configuration and encounter summary hide are available on clinical forms (LBF layouts)
            return this.formId && this.formId.indexOf('LBF') === 0;
        },

        loadConfigs: function (callback) {
            var self = this;
            var apiUrl = this.moduleBaseUrl + '/public/api/get_config.php';

            $.ajax({
                url: apiUrl,
                type: 'GET',
                data: {
                    form_id: this.formId,
                    is_admin: 1
                },
                dataType: 'json',
                success: function (res) {
                    self.configs = {};
                    self.hiddenFields = {};

                    if (res && res.success) {
                        if (Array.isArray(res.configs)) {
                            res.configs.forEach(function (cfg) {
                                self.configs[cfg.group_id] = cfg;
                            });
                        }

                        if (Array.isArray(res.hidden_fields)) {
                            res.hidden_fields.forEach(function (fld) {
                                self.hiddenFields[String(fld).trim()] = true;
                            });
                        }
                    }

                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function (err) {
                    console.warn('Group Canvas: Failed to load layout canvas configs', err);
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },

        injectToolbarButtons: function () {
            var self = this;

            // Iterate over each navbar header directly rather than .group container
            // to avoid issue with unclosed nested .group DIVs in OpenEMR legacy markup
            $('nav.navbar').each(function () {
                var nav = $(this);

                // Find the delete button to extract the exact group_id for this specific header
                var deleteBtn = nav.find('button.deletegroup').first();
                var addBtn = nav.find('button.addfield').first();
                var groupId = '';

                if (deleteBtn.length && deleteBtn.attr('id')) {
                    groupId = String(deleteBtn.attr('id')).trim();
                } else if (addBtn.length && addBtn.attr('id')) {
                    groupId = String(addBtn.attr('id')).replace(/^addto~/, '').trim();
                }

                if (!groupId) return;

                // Locate the toolbar inside THIS navbar directly
                var toolbar = nav.find('> .btn-toolbar, .btn-toolbar').first();
                if (!toolbar.length) return;

                // Avoid duplicate injection
                if (toolbar.find('.oe-layout-canvas-group').length) return;

                // Extract group title
                var groupTitle = nav.find('.navbar-brand').first().text().trim() || ('Group ' + groupId);

                // Check if image is configured specifically for THIS groupId
                var cfg = self.configs[groupId];
                var hasImage = !!(cfg && cfg.has_image && cfg.background_image);

                var btnClass = hasImage ? 'btn btn-primary btn-sm oe-btn-layout-canvas' : 'btn btn-secondary btn-sm oe-btn-layout-canvas';
                var badgeHtml = hasImage ? '<span class="badge badge-light ml-1 oe-image-status-badge text-primary font-weight-bold"><i class="fa fa-check"></i> Image Set</span>' : '';

                var buttonGroupHtml = $(
                    '<div class="btn-group ml-2 oe-layout-canvas-group" role="group" aria-label="Canvas Image">' +
                    '  <button type="button" class="' + btnClass + '" data-group-id="' + groupId + '" data-group-title="' + self.escapeHtml(groupTitle) + '" title="Configure Canvas Image for this group header">' +
                    '    <i class="fa fa-palette mr-1"></i> <span>Canvas Image</span>' +
                    '    ' + badgeHtml +
                    '  </button>' +
                    '</div>'
                );

                buttonGroupHtml.find('button').on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.openConfigModal(groupId, groupTitle);
                });

                // Append to toolbar in the area right after Group Options
                var groupOptions = toolbar.find('div.btn-group[aria-label="Group Options"]').first();
                if (groupOptions.length) {
                    groupOptions.after(buttonGroupHtml);
                } else {
                    toolbar.append(buttonGroupHtml);
                }
            });
        },

        /* ==========================================================================
           Encounter Summary Hide - Column & Checkbox Injection (Clinical Forms Only)
           ========================================================================== */
        injectEncounterSummaryHideColumns: function () {
            var self = this;
            if (!this.isClinicalForm()) return;

            // 1. Inject Header into each table: directly after "Backup List" <th>
            $('th').each(function () {
                var th = $(this);
                var text = th.text().trim();
                if (/backup\s*list/i.test(text)) {
                    if (!th.next('.oe-enc-summary-hide-th').length) {
                        var newTh = $(
                            '<th class="oe-enc-summary-hide-th" title="Check this box to hide this field in the Encounter Summary / Visit Summary">' +
                            '  <i class="fa fa-eye-slash mr-1 text-danger"></i> Encounter Summary Hide' +
                            '</th>'
                        );
                        th.after(newTh);
                    }
                }
            });

            // 2. Inject Checkbox <td> into each field row: directly after "Backup List" <td>
            $('td').has('input[name*="[list_backup_id]"]').each(function () {
                var backupTd = $(this);
                if (backupTd.next('.oe-enc-summary-hide-td').length) return;

                var tr = backupTd.closest('tr');
                var idInput = tr.find('input[name$="[id]"], input[name$="[originalid]"]').first();
                var fieldId = idInput.length ? String(idInput.val()).trim() : '';

                if (!fieldId) return;

                var isHidden = !!(self.hiddenFields && self.hiddenFields[fieldId]);
                var checkedAttr = isHidden ? ' checked="checked"' : '';

                var hideTd = $(
                    '<td class="text-center optcell oe-enc-summary-hide-td">' +
                    '  <div class="oe-enc-hide-wrapper" title="Check to hide \'' + self.escapeHtml(fieldId) + '\' from Encounter Summary">' +
                    '    <input type="checkbox" class="oe-enc-summary-hide-cb" id="oe_enc_hide_' + self.escapeHtml(fieldId) + '" data-form-id="' + self.escapeHtml(self.formId) + '" data-field-id="' + self.escapeHtml(fieldId) + '"' + checkedAttr + ' />' +
                    '  </div>' +
                    '</td>'
                );

                backupTd.after(hideTd);
            });

            // 3. Inject into "#fielddetail" (Add New Field modal table)
            var newBackupTd = $('#fielddetail td').has('#newbackuplistid');
            if (newBackupTd.length && !newBackupTd.next('.oe-enc-summary-hide-td').length) {
                var newHideTd = $(
                    '<td class="text-center optcell oe-enc-summary-hide-td">' +
                    '  <div class="oe-enc-hide-wrapper" title="Check to hide newly created field in Encounter Summary">' +
                    '    <input type="checkbox" id="new_enc_summary_hide" name="new_enc_summary_hide" class="oe-enc-summary-hide-cb" />' +
                    '  </div>' +
                    '</td>'
                );
                newBackupTd.after(newHideTd);
            }
        },

        bindHiddenFieldEvents: function () {
            var self = this;

            // Handle Checkbox Toggle
            $(document).off('change.oeEncHide').on('change.oeEncHide', '.oe-enc-summary-hide-cb:not(#new_enc_summary_hide)', function (e) {
                var cb = $(this);
                var formId = cb.data('form-id') || self.formId;
                var fieldId = cb.data('field-id');
                var isHidden = cb.is(':checked') ? 1 : 0;

                if (!formId || !fieldId) return;

                var wrapper = cb.closest('.oe-enc-hide-wrapper');
                wrapper.addClass('oe-saving-pulse');

                var apiUrl = self.moduleBaseUrl + '/public/api/save_hidden_field.php';

                $.ajax({
                    url: apiUrl,
                    type: 'POST',
                    data: {
                        form_id: formId,
                        field_id: fieldId,
                        is_hidden: isHidden,
                        csrf_token_form: self.csrfToken
                    },
                    dataType: 'json',
                    success: function (res) {
                        wrapper.removeClass('oe-saving-pulse');
                        if (res && res.success) {
                            if (isHidden) {
                                self.hiddenFields[fieldId] = true;
                                self.showToast('"' + fieldId + '" is now HIDDEN in Encounter Summary', 'success');
                            } else {
                                delete self.hiddenFields[fieldId];
                                self.showToast('"' + fieldId + '" is now VISIBLE in Encounter Summary', 'info');
                            }
                        } else {
                            cb.prop('checked', !isHidden); // revert on failure
                            self.showToast(res.message || 'Error updating Encounter Summary visibility', 'danger');
                        }
                    },
                    error: function (xhr, status, error) {
                        wrapper.removeClass('oe-saving-pulse');
                        cb.prop('checked', !isHidden); // revert
                        self.showToast('Server communication error: ' + error, 'danger');
                    }
                });
            });

            // Handle Add New Field - persist hidden field setting if checked
            $(document).off('click.oeEncHideNew').on('click.oeEncHideNew', '.savenewfield', function () {
                var isHidden = $('#new_enc_summary_hide').is(':checked');
                var newId = ($('#newid').val() || '').trim();
                if (isHidden && newId) {
                    $.ajax({
                        url: self.moduleBaseUrl + '/public/api/save_hidden_field.php',
                        type: 'POST',
                        data: {
                            form_id: self.formId,
                            field_id: newId,
                            is_hidden: 1,
                            csrf_token_form: self.csrfToken
                        },
                        dataType: 'json'
                    });
                }
            });
        },

        showToast: function (message, type) {
            var container = $('#oe_layout_notification_container');
            if (!container.length) {
                container = $('<div id="oe_layout_notification_container" class="oe-layout-notification-container"></div>');
                $('body').append(container);
            }

            var iconClass = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle');
            var toast = $(
                '<div class="oe-layout-toast oe-toast-' + type + '">' +
                '  <i class="fa ' + iconClass + ' mr-2"></i>' +
                '  <span>' + this.escapeHtml(message) + '</span>' +
                '</div>'
            );

            container.append(toast);

            setTimeout(function () {
                toast.addClass('show');
            }, 20);

            setTimeout(function () {
                toast.removeClass('show');
                setTimeout(function () {
                    toast.remove();
                }, 300);
            }, 3200);
        },

        ensureModalDOM: function () {
            if ($('#oe_layout_canvas_modal_overlay').length) return;

            var modalHtml = 
                '<div id="oe_layout_canvas_modal_overlay" class="oe-layout-canvas-overlay">' +
                '  <div class="oe-layout-canvas-modal">' +
                '    <div class="oe-layout-canvas-modal-header">' +
                '      <h5 class="oe-layout-canvas-modal-title">' +
                '        <i class="fa fa-palette text-primary mr-1"></i> <span id="oe_modal_header_title">Group Canvas Image Configuration</span>' +
                '      </h5>' +
                '      <button type="button" class="oe-layout-canvas-close" id="oe_modal_close_btn">&times;</button>' +
                '    </div>' +
                '    <div class="oe-layout-canvas-modal-body">' +
                '      <div id="oe_modal_alert" class="alert d-none mb-3"></div>' +
                '      <form id="oe_layout_canvas_form" enctype="multipart/form-data">' +
                '        <input type="hidden" name="form_id" id="oe_cfg_form_id" value="" />' +
                '        <input type="hidden" name="group_id" id="oe_cfg_group_id" value="" />' +
                '        <input type="hidden" name="existing_image" id="oe_cfg_existing_image" value="" />' +
                '        <input type="hidden" name="csrf_token_form" value="' + this.csrfToken + '" />' +
                '        ' +
                '        <div class="row mb-3">' +
                '          <div class="col-md-6">' +
                '            <label class="font-weight-bold text-muted small mb-1">Form ID:</label>' +
                '            <div id="oe_disp_form_id" class="form-control-plaintext font-weight-bold text-dark py-0"></div>' +
                '          </div>' +
                '          <div class="col-md-6">' +
                '            <label class="font-weight-bold text-muted small mb-1">Group Name / ID:</label>' +
                '            <div id="oe_disp_group_name" class="form-control-plaintext font-weight-bold text-dark py-0"></div>' +
                '          </div>' +
                '        </div>' +
                '        ' +
                '        <!-- Existing Image Preview -->' +
                '        <div id="oe_preview_section" class="mb-3 d-none">' +
                '          <label class="font-weight-bold text-muted small mb-1">Current Canvas Diagram Image:</label>' +
                '          <div class="oe-image-preview-card">' +
                '            <div class="oe-image-preview-wrapper">' +
                '              <img id="oe_current_image_preview" src="" alt="Canvas Preview" />' +
                '            </div>' +
                '            <div class="oe-image-preview-meta mt-2 d-flex justify-content-between align-items-center">' +
                '              <div>' +
                '                <span class="badge badge-success mr-2"><i class="fa fa-check"></i> Image Active</span>' +
                '                <span id="oe_current_image_name" class="small text-muted font-italic"></span>' +
                '              </div>' +
                '              <button type="button" class="btn btn-sm btn-danger" id="oe_remove_image_btn">' +
                '                <i class="fa fa-trash-alt mr-1"></i> Remove Image' +
                '              </button>' +
                '            </div>' +
                '          </div>' +
                '        </div>' +
                '        ' +
                '        <!-- Upload New Image -->' +
                '        <div class="form-group mb-3">' +
                '          <label class="font-weight-bold text-muted small mb-1" id="oe_upload_label">Upload Canvas Background Image:</label>' +
                '          <div class="oe-file-dropzone" id="oe_dropzone">' +
                '            <i class="fa fa-cloud-upload-alt fa-2x text-muted mb-2"></i>' +
                '            <p class="mb-1 font-weight-bold">Drag & drop canvas image here, or <span class="text-primary cursor-pointer">browse file</span></p>' +
                '            <p class="text-muted small mb-0">Supported formats: PNG, JPG, JPEG, SVG, WEBP (Medical diagram / anatomy template)</p>' +
                '          </div>' +
                '          <input type="file" name="background_image" id="oe_cfg_file_input" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif" class="d-none" />' +
                '          <div id="oe_selected_file_info" class="mt-2 small text-success font-weight-bold d-none"></div>' +
                '        </div>' +
                '        ' +
                '        <!-- Dimensions & Button Label -->' +
                '        <div class="row">' +
                '          <div class="col-md-6">' +
                '            <div class="form-group mb-2">' +
                '              <label for="oe_cfg_button_label" class="font-weight-bold small text-muted">Form Header Button Label:</label>' +
                '              <input type="text" class="form-control form-control-sm" name="button_label" id="oe_cfg_button_label" placeholder="Annotate Diagram" />' +
                '              <small class="form-text text-muted">Label shown on the clinical form header button.</small>' +
                '            </div>' +
                '          </div>' +
                '          <div class="col-md-3">' +
                '            <div class="form-group mb-2">' +
                '              <label for="oe_cfg_width" class="font-weight-bold small text-muted">Canvas Width (px):</label>' +
                '              <input type="number" class="form-control form-control-sm" name="canvas_width" id="oe_cfg_width" value="800" min="200" max="2400" />' +
                '            </div>' +
                '          </div>' +
                '          <div class="col-md-3">' +
                '            <div class="form-group mb-2">' +
                '              <label for="oe_cfg_height" class="font-weight-bold small text-muted">Canvas Height (px):</label>' +
                '              <input type="number" class="form-control form-control-sm" name="canvas_height" id="oe_cfg_height" value="600" min="200" max="2400" />' +
                '            </div>' +
                '          </div>' +
                '        </div>' +
                '      </form>' +
                '    </div>' +
                '    <div class="oe-layout-canvas-modal-footer">' +
                '      <div id="oe_modal_status" class="small text-muted"></div>' +
                '      <div>' +
                '        <button type="button" class="btn btn-secondary btn-sm mr-2" id="oe_modal_cancel_btn">Close</button>' +
                '        <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="oe_modal_save_btn">' +
                '          <i class="fa fa-save mr-1"></i> Save' +
                '        </button>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>';

            $('body').append(modalHtml);
            this.bindModalEvents();
        },

        bindModalEvents: function () {
            var self = this;

            // Close modal handlers
            $('#oe_modal_close_btn, #oe_modal_cancel_btn').on('click', function () {
                $('#oe_layout_canvas_modal_overlay').removeClass('active');
            });

            // Prevent click bubbling loop on file input
            $('#oe_cfg_file_input').on('click', function (e) {
                e.stopPropagation();
            });

            // Click on dropzone triggers file input
            $('#oe_dropzone').on('click', function (e) {
                if (e.target && e.target.id === 'oe_cfg_file_input') return;
                var fileInput = document.getElementById('oe_cfg_file_input');
                if (fileInput) {
                    fileInput.click();
                }
            });

            // Drag and drop events
            $('#oe_dropzone')
                .on('dragover dragenter', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('dragover');
                })
                .on('dragleave dragend drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                });

            $('#oe_dropzone').on('drop', function (e) {
                var dt = e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    $('#oe_cfg_file_input')[0].files = dt.files;
                    self.handleFileSelection(dt.files[0]);
                }
            });

            $('#oe_cfg_file_input').on('change', function () {
                if (this.files && this.files.length) {
                    self.handleFileSelection(this.files[0]);
                }
            });

            // Save button
            $('#oe_modal_save_btn').on('click', function () {
                self.saveCanvasConfig();
            });

            // Remove image button
            $('#oe_remove_image_btn').on('click', function () {
                self.removeCanvasImage();
            });
        },

        handleFileSelection: function (file) {
            if (!file) return;
            $('#oe_selected_file_info').removeClass('d-none').html(
                '<i class="fa fa-file-image mr-1"></i> Selected: ' + this.escapeHtml(file.name) + ' (' + Math.round(file.size / 1024) + ' KB)'
            );
        },

        openConfigModal: function (groupId, groupTitle) {
            var self = this;
            this.activeGroupId = groupId;
            this.activeGroupTitle = groupTitle;

            $('#oe_modal_alert').addClass('d-none').removeClass('alert-success alert-danger');
            $('#oe_modal_status').text('');
            $('#oe_cfg_form_id').val(this.formId);
            $('#oe_cfg_group_id').val(groupId);
            $('#oe_disp_form_id').text(this.formId);
            $('#oe_disp_group_name').text(groupTitle + ' (ID: ' + groupId + ')');
            $('#oe_modal_header_title').text('Canvas Image - ' + groupTitle);
            $('#oe_cfg_file_input').val('');
            $('#oe_selected_file_info').addClass('d-none').empty();

            var cfg = this.configs[groupId] || null;

            if (cfg && cfg.has_image && cfg.background_image_url) {
                $('#oe_preview_section').removeClass('d-none');
                $('#oe_current_image_preview').attr('src', cfg.background_image_url);
                $('#oe_current_image_name').text(cfg.background_image || '');
                $('#oe_cfg_existing_image').val(cfg.background_image || '');
                $('#oe_upload_label').text('Replace Canvas Background Image:');
                $('#oe_cfg_button_label').val(cfg.button_label || 'Annotate Diagram');
                $('#oe_cfg_width').val(cfg.canvas_width || 800);
                $('#oe_cfg_height').val(cfg.canvas_height || 600);
            } else {
                $('#oe_preview_section').addClass('d-none');
                $('#oe_current_image_preview').attr('src', '');
                $('#oe_current_image_name').text('');
                $('#oe_cfg_existing_image').val('');
                $('#oe_upload_label').text('Upload Canvas Background Image:');
                $('#oe_cfg_button_label').val('Annotate Diagram');
                $('#oe_cfg_width').val(800);
                $('#oe_cfg_height').val(600);
            }

            $('#oe_layout_canvas_modal_overlay').addClass('active');
        },

        saveCanvasConfig: function () {
            var self = this;
            var formElem = document.getElementById('oe_layout_canvas_form');
            var formData = new FormData(formElem);
            var saveBtn = $('#oe_modal_save_btn');
            var alertBox = $('#oe_modal_alert');
            var statusElem = $('#oe_modal_status');

            saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
            statusElem.text('Uploading & saving configuration...');
            alertBox.addClass('d-none');

            var saveUrl = this.moduleBaseUrl + '/public/api/save_config.php';

            $.ajax({
                url: saveUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save');
                    if (res && res.success) {
                        alertBox.removeClass('d-none alert-danger').addClass('alert-success').text(res.message || 'Configuration saved successfully!');
                        statusElem.text('Saved.');

                        // Update local config cache
                        var groupId = self.activeGroupId;
                        var hasImage = !!(res.has_image && res.image);
                        self.configs[groupId] = {
                            form_id: self.formId,
                            group_id: groupId,
                            button_label: res.button_label || 'Annotate Diagram',
                            background_image: res.image || '',
                            background_image_url: res.image_url || '',
                            has_image: hasImage,
                            canvas_width: res.canvas_width || 800,
                            canvas_height: res.canvas_height || 600
                        };

                        // Update toolbar button UI live for THIS groupId only
                        self.updateToolbarButtonUI(groupId, hasImage);

                        setTimeout(function () {
                            $('#oe_layout_canvas_modal_overlay').removeClass('active');
                        }, 1000);
                    } else {
                        alertBox.removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Failed to save configuration');
                        statusElem.text('Error.');
                    }
                },
                error: function (xhr, status, error) {
                    saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save');
                    alertBox.removeClass('d-none alert-success').addClass('alert-danger').text('Network or server error: ' + error);
                    statusElem.text('Save error.');
                }
            });
        },

        removeCanvasImage: function () {
            var self = this;
            if (!confirm('Are you sure you want to remove the canvas diagram image for this group?')) {
                return;
            }

            var groupId = this.activeGroupId;
            var deleteUrl = this.moduleBaseUrl + '/public/api/delete_config.php';
            var alertBox = $('#oe_modal_alert');
            var statusElem = $('#oe_modal_status');
            var csrfToken = $('input[name="csrf_token_form"]').val() || this.csrfToken;

            statusElem.text('Removing image...');

            $.ajax({
                url: deleteUrl,
                type: 'POST',
                data: {
                    form_id: this.formId,
                    group_id: groupId,
                    csrf_token_form: csrfToken
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        alertBox.removeClass('d-none alert-danger').addClass('alert-success').text('Canvas image removed successfully');
                        statusElem.text('Removed.');

                        // Clear modal preview UI
                        $('#oe_preview_section').addClass('d-none');
                        $('#oe_current_image_preview').attr('src', '');
                        $('#oe_current_image_name').text('');
                        $('#oe_cfg_existing_image').val('');
                        $('#oe_upload_label').text('Upload Canvas Background Image:');
                        $('#oe_cfg_file_input').val('');
                        $('#oe_selected_file_info').addClass('d-none').empty();

                        // Clear local config
                        if (self.configs[groupId]) {
                            self.configs[groupId].has_image = false;
                            self.configs[groupId].background_image = '';
                            self.configs[groupId].background_image_url = '';
                        }

                        // Update toolbar button UI live
                        self.updateToolbarButtonUI(groupId, false);

                        setTimeout(function () {
                            $('#oe_layout_canvas_modal_overlay').removeClass('active');
                        }, 800);
                    } else {
                        alertBox.removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Failed to remove canvas image');
                        statusElem.text('Error.');
                    }
                },
                error: function () {
                    alertBox.removeClass('d-none alert-success').addClass('alert-danger').text('Server error while removing image');
                    statusElem.text('Error.');
                }
            });
        },

        updateToolbarButtonUI: function (groupId, hasImage) {
            var btn = $('button.oe-btn-layout-canvas[data-group-id="' + groupId + '"]');
            if (btn.length) {
                if (hasImage) {
                    btn.removeClass('btn-secondary btn-outline-secondary btn-info').addClass('btn-primary');
                    if (!btn.find('.oe-image-status-badge').length) {
                        btn.append('<span class="badge badge-light ml-1 oe-image-status-badge text-primary font-weight-bold"><i class="fa fa-check"></i> Image Set</span>');
                    }
                } else {
                    btn.removeClass('btn-primary btn-info btn-success').addClass('btn-secondary');
                    btn.find('.oe-image-status-badge').remove();
                }
            }
        },

        escapeHtml: function (str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Initialize Layout Canvas Editor
    LayoutCanvasEditor.init();

})(jQuery);
