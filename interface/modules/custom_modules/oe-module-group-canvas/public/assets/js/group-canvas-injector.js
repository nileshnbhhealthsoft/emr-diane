/**
 * group-canvas-injector.js
 *
 * Injects Group Header Canvas Buttons and Image Display Cards into Encounter Layout Forms
 * and Visit Summary (forms.php / report.php), and manages the interactive Canvas Modal Lifecycle.
 *
 * @package OpenEMR
 * @author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 */

(function ($) {
    'use strict';

    var GroupCanvasManager = {
        formId: '',
        pid: 0,
        encounter: 0,
        formInstanceId: 0,
        csrfToken: '',
        moduleBaseUrl: '',
        isVisitSummary: false,
        formConfigs: {}, // formId -> configs array
        activeDrawer: null,
        currentModalContext: null,

        init: function () {
            var self = this;
            $(document).ready(function () {
                self.detectContext();
                self.ensureModalDOM();

                // Immediately set default provider if auth context is present
                if (window.oeModuleAuthUser) {
                    self.setDefaultProvider(window.oeModuleAuthUser);
                }

                if (self.isVisitSummary) {
                    self.initVisitSummary();
                } else if (self.formId) {
                    self.loadSingleFormConfigs(self.formId);
                } else {
                    // Fallback: check if any form-holders exist on the page
                    if ($('.form-holder, #partable').length) {
                        self.isVisitSummary = true;
                        self.initVisitSummary();
                    } else if ($('select[name="form_provider_id"], select[name="form_fs_provid"], select[name^="form_"][name*="provider"]').length) {
                        self.loadSingleFormConfigs('LBF');
                    }
                }
            });
        },

        detectContext: function () {
            var urlParams = new URLSearchParams(window.location.search);
            var pathname = window.location.pathname;

            // 1. Detect Visit Summary Mode
            if (pathname.indexOf('forms.php') !== -1 || $('#partable').length > 0 || $('.form-holder').length > 0) {
                this.isVisitSummary = true;
            }

            // 2. Detect Patient ID (PID)
            var detectedPid = $('input[name="pid"], input[name="form_pid"], input[name="patientid"], input[name="pId"]').val() ||
                urlParams.get('patientid') ||
                urlParams.get('pid') ||
                (typeof pid !== 'undefined' ? pid : 0) ||
                (typeof window.pid !== 'undefined' ? window.pid : 0) ||
                (window.parent && typeof window.parent.pid !== 'undefined' ? window.parent.pid : 0) ||
                (window.top && typeof window.top.pid !== 'undefined' ? window.top.pid : 0) ||
                (window.top && typeof window.top.patient_id !== 'undefined' ? window.top.patient_id : 0) ||
                (window.top && typeof window.top.current_pid !== 'undefined' ? window.top.current_pid : 0);
            this.pid = parseInt(detectedPid, 10) || 0;

            // 3. Detect Encounter ID
            var detectedEnc = $('input[name="encounter"], input[name="form_encounter"], input[name="visitid"], input[name="encounter_id"]').val() ||
                urlParams.get('visitid') ||
                urlParams.get('encounter') ||
                (typeof encounter !== 'undefined' ? encounter : 0) ||
                (typeof window.encounter !== 'undefined' ? window.encounter : 0) ||
                (window.parent && typeof window.parent.encounter !== 'undefined' ? window.parent.encounter : 0) ||
                (window.top && typeof window.top.encounter !== 'undefined' ? window.top.encounter : 0) ||
                (window.top && typeof window.top.current_encounter !== 'undefined' ? window.top.current_encounter : 0) ||
                (window.top && typeof window.top.encounter_id !== 'undefined' ? window.top.encounter_id : 0);
            this.encounter = parseInt(detectedEnc, 10) || 0;

            // 4. Detect Form ID for single-form mode
            this.formId = $('input[name="formname"]').val() ||
                $('input[name="form_id"]').val() ||
                $('input[name="layout_id"]').val() ||
                urlParams.get('formname') ||
                urlParams.get('form_id') ||
                urlParams.get('layout_id') ||
                urlParams.get('form') ||
                '';

            if (!this.formId && pathname.indexOf('demographics') !== -1) {
                this.formId = 'DEM';
            }
            if (!this.formId && pathname.indexOf('history') !== -1) {
                this.formId = 'HIS';
            }

            // 5. Detect Form Instance ID
            this.formInstanceId = parseInt(
                $('input[name="id"]').val() ||
                $('input[name="formid"]').val() ||
                $('input[name="form_instance_id"]').val() ||
                urlParams.get('id') ||
                urlParams.get('formid') || 0,
                10
            ) || 0;

            // 5b. Fallback: check form action URL parameters
            var formAction = $('form').first().attr('action') || '';
            if (formAction && formAction.indexOf('?') !== -1) {
                try {
                    var formActionParams = new URLSearchParams(formAction.substring(formAction.indexOf('?')));
                    if (!this.formId) {
                        this.formId = formActionParams.get('formname') || formActionParams.get('form_id') || formActionParams.get('layout_id') || formActionParams.get('form') || '';
                    }
                    if (!this.formInstanceId) {
                        this.formInstanceId = parseInt(formActionParams.get('id') || formActionParams.get('formid') || '0', 10) || 0;
                    }
                    if (!this.encounter) {
                        this.encounter = parseInt(formActionParams.get('visitid') || formActionParams.get('encounter') || '0', 10) || 0;
                    }
                    if (!this.pid) {
                        this.pid = parseInt(formActionParams.get('pid') || formActionParams.get('patientid') || '0', 10) || 0;
                    }
                } catch (eAction) { }
            }

            // 6. CSRF Token
            this.csrfToken = $('input[name="csrf_token_form"]').val() ||
                $('input[name="csrf_token"]').val() ||
                (typeof csrf_token_form !== 'undefined' ? csrf_token_form : '') ||
                (window.parent && typeof window.parent.csrf_token_form !== 'undefined' ? window.parent.csrf_token_form : '') ||
                (window.top && typeof window.top.csrf_token_form !== 'undefined' ? window.top.csrf_token_form : '');

            // 7. Base URL of module
            var webroot = (typeof top.webroot !== 'undefined' ? top.webroot : '') ||
                (typeof window.webroot !== 'undefined' ? window.webroot : '') || '';
            if (!webroot) {
                var match = window.location.pathname.match(/^(\/[^\/]+)/);
                webroot = match ? match[1] : '';
            }
            this.moduleBaseUrl = webroot + '/interface/modules/custom_modules/oe-module-group-canvas';
        },

        /* ==========================================================================
           Single Form Mode (LBF new.php / view.php / DEM / HIS / etc.)
           ========================================================================== */
        loadSingleFormConfigs: function (formId) {
            var self = this;
            var apiUrl = this.moduleBaseUrl + '/public/api/get_config.php';

            $.ajax({
                url: apiUrl,
                type: 'GET',
                data: {
                    form_id: formId,
                    form_instance_id: this.formInstanceId,
                    pid: this.pid,
                    encounter: this.encounter
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        if (res.pid && (!self.pid || self.pid <= 0)) {
                            self.pid = parseInt(res.pid, 10) || self.pid;
                        }
                        if (res.encounter && (!self.encounter || self.encounter <= 0)) {
                            self.encounter = parseInt(res.encounter, 10) || self.encounter;
                        }
                        self.setDefaultProvider(res);
                        if (Array.isArray(res.configs)) {
                            self.formConfigs[formId] = res.configs;
                            self.injectSingleFormComponents(formId, res.configs);
                            self.bindFormSubmitSync(formId);
                        }
                    }
                },
                error: function (err) {
                    console.warn('Group Canvas: Failed to load form configurations', err);
                }
            });
        },

        setDefaultProvider: function (res) {
            if (res) {
                this.userAuthInfo = res;
            } else {
                res = this.userAuthInfo || window.oeModuleAuthUser || {};
            }

            // Only set default provider for new forms (when no formInstanceId exists or provider is unselected)
            if (this.formInstanceId && this.formInstanceId > 0) {
                return;
            }

            var authUserId = (res && res.auth_user_id) ? String(res.auth_user_id) : '';
            var authUserName = (res && res.auth_user_name) ? String(res.auth_user_name) : '';
            var authUserFullname = (res && res.auth_user_fullname) ? String(res.auth_user_fullname).toLowerCase() : '';
            var authProvider = (res && res.auth_provider) ? String(res.auth_provider).toLowerCase() : '';

            var applyToSelect = function (sel) {
                var currentVal = sel.val();
                if (!currentVal || currentVal === '0' || currentVal === '') {
                    if (authUserId && sel.find('option[value="' + authUserId + '"]').length) {
                        sel.val(authUserId).trigger('change');
                    } else if (authUserFullname || authProvider || authUserName) {
                        sel.find('option').each(function () {
                            var opt = $(this);
                            var optVal = opt.val();
                            if (!optVal || optVal === '0') return;
                            var optText = opt.text().toLowerCase();
                            if ((authUserFullname && optText.indexOf(authUserFullname) !== -1) ||
                                (authProvider && optText.indexOf(authProvider) !== -1) ||
                                (authUserName && optText.indexOf(authUserName.toLowerCase()) !== -1)) {
                                sel.val(optVal).trigger('change');
                                return false;
                            }
                        });
                    }
                }
            };

            // 1. Top-level Provider selector on clinical form: select[name="form_provider_id"]
            var formProvSelect = $('select[name="form_provider_id"]');
            if (formProvSelect.length) {
                applyToSelect(formProvSelect);
            }

            // 2. Fee Sheet Main Provider: select[name="form_fs_provid"]
            var fsProvSelect = $('select[name="form_fs_provid"]');
            if (fsProvSelect.length) {
                applyToSelect(fsProvSelect);
            }

            // 3. Any in-form Provider layout fields (data_type 10/11 or named provider)
            $('select[name^="form_"][name*="provider"], select[name^="form_"][name*="user"]').each(function () {
                applyToSelect($(this));
            });
        },

        injectSingleFormComponents: function (formId, configs) {
            var self = this;

            configs.forEach(function (cfg) {
                if (!cfg.has_image || !cfg.background_image_url) {
                    return;
                }

                var groupId = cfg.group_id;
                var btnLabel = cfg.button_label || 'Annotate Diagram';
                var hasDrawing = cfg.has_drawing;
                var displayImgUrl = (hasDrawing && cfg.drawing_png) ? cfg.drawing_png : cfg.background_image_url;

                // 1. Inject hidden inputs into form to ensure data travels with form submission
                self.ensureFormHiddenInputs(formId, groupId, cfg.drawing_png || '');

                // 2. Inject Compact Canvas Button into Group Header
                var targetHeader = self.findGroupHeaderElement(formId, groupId);
                if (targetHeader && targetHeader.length) {
                    if (!targetHeader.find('.oe-group-canvas-wrapper[data-group="' + groupId + '"]').length &&
                        !targetHeader.parent().find('.oe-group-canvas-wrapper[data-group="' + groupId + '"]').length) {
                        var btnClass = 'btn btn-sm ' + (hasDrawing ? 'btn-success has-saved-data' : 'btn-primary') + ' oe-group-canvas-btn';
                        var icon = hasDrawing ? 'fa-check-circle' : 'fa-paint-brush';
                        var badgeHtml = hasDrawing ? '<span class="oe-group-canvas-badge">Saved</span>' : '';

                        var wrapper = $(
                            '<span class="oe-group-canvas-wrapper ml-2" data-form="' + formId + '" data-group="' + groupId + '">' +
                            '  <button type="button" class="' + btnClass + '" data-form="' + formId + '" data-group="' + groupId + '" title="Open interactive canvas for ' + self.escapeHtml(btnLabel) + '">' +
                            '    <i class="fa ' + icon + ' mr-1"></i>' +
                            '    <span>' + self.escapeHtml(btnLabel) + '</span>' +
                            '    ' + badgeHtml +
                            '  </button>' +
                            '</span>'
                        );

                        wrapper.find('button').on('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            self.openModal({
                                form_id: formId,
                                group_id: groupId,
                                form_instance_id: self.formInstanceId,
                                pid: self.pid,
                                encounter: self.encounter,
                                button_label: cfg.button_label,
                                background_image_url: cfg.background_image_url,
                                canvas_width: cfg.canvas_width,
                                canvas_height: cfg.canvas_height,
                                has_drawing: cfg.has_drawing,
                                drawing_png: cfg.drawing_png
                            });
                        });

                        if (targetHeader.is('label')) {
                            targetHeader.after(wrapper);
                        } else {
                            targetHeader.append(wrapper);
                        }
                    }
                }

                // 3. Inject Image Card into Group Section
                var cardId = 'oe_group_image_card_' + formId + '_' + groupId;
                if (!document.getElementById(cardId)) {
                    var targetSection = self.findGroupSectionElement(formId, groupId);
                    var cardStatusBadge = hasDrawing
                        ? '<span class="badge badge-success oe-card-badge"><i class="fa fa-check-circle mr-1"></i> Annotated & Saved</span>'
                        : '<span class="badge badge-info oe-card-badge"><i class="fa fa-image mr-1"></i> Diagram Template</span>';

                    var imageCard = $(
                        //'<div class="oe-group-canvas-image-card" id="' + cardId + '" data-form="' + formId + '" data-group="' + groupId + '">' +
                        //'  <div class="oe-card-top-bar">' +
                        //'    <div class="oe-card-title">' +
                        //'      <i class="fa fa-palette text-primary mr-1"></i>' +
                        //'      <span>' + self.escapeHtml(btnLabel) + '</span>' +
                        //'    </div>' +
                        //'    <div class="oe-card-meta">' +
                        //'      <span id="oe_card_badge_' + formId + '_' + groupId + '">' + cardStatusBadge + '</span>' +
                        //'      <button type="button" class="btn btn-sm btn-primary ml-2 oe-card-open-canvas-btn" data-form="' + formId + '" data-group="' + groupId + '">' +
                        //'        <i class="fa fa-pen-fancy mr-1"></i> Canvas' +
                        //'      </button>' +
                        //'    </div>' +
                        //'  </div>' +
                        //'  <div class="oe-card-image-wrapper" title="Click to open canvas and annotate">' +
                        //'    <img src="' + displayImgUrl + '" class="oe-group-display-img" id="oe_group_display_img_' + formId + '_' + groupId + '" alt="' + self.escapeHtml(btnLabel) + '" />' +
                        //'    <div class="oe-card-hover-overlay">' +
                        //'      <div class="oe-card-overlay-content">' +
                        //'        <i class="fa fa-expand-arrows-alt fa-2x mb-1"></i>' +
                        //'        <span class="font-weight-bold">Click to Open Canvas & Annotate</span>' +
                        //'      </div>' +
                        //'    </div>' +
                        //'  </div>' +
                        //'</div>'
                    );

                    imageCard.on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        self.openModal({
                            form_id: formId,
                            group_id: groupId,
                            form_instance_id: self.formInstanceId,
                            pid: self.pid,
                            encounter: self.encounter,
                            button_label: cfg.button_label,
                            background_image_url: cfg.background_image_url,
                            canvas_width: cfg.canvas_width,
                            canvas_height: cfg.canvas_height,
                            has_drawing: cfg.has_drawing,
                            drawing_png: cfg.drawing_png
                        });
                    });

                    if (targetSection && targetSection.length) {
                        targetSection.prepend(imageCard);
                    } else if (targetHeader && targetHeader.length) {
                        targetHeader.after(imageCard);
                    } else {
                        $('form').first().prepend(imageCard);
                    }
                }
            });
        },

        ensureFormHiddenInputs: function (formId, groupId, pngData) {
            var form = $('form').first();
            if (!form.length) return;

            var inputId = 'oe_canvas_png_input_' + formId + '_' + groupId;
            if (!$('#' + inputId).length) {
                var hidden = $('<input type="hidden" name="oe_group_canvas_png[' + groupId + ']" id="' + inputId + '" value="' + (pngData || '') + '" />');
                form.append(hidden);
            }
        },

        bindFormSubmitSync: function (formId) {
            var self = this;
            $('form').on('submit', function () {
                // Ensure hidden fields contain latest data if modal was edited
                if (self.formConfigs[formId]) {
                    self.formConfigs[formId].forEach(function (cfg) {
                        var input = $('#oe_canvas_png_input_' + formId + '_' + cfg.group_id);
                        if (input.length && cfg.drawing_png) {
                            input.val(cfg.drawing_png);
                        }
                    });
                }
            });
        },

        /* ==========================================================================
           Visit Summary Mode (forms.php / report.php)
           ========================================================================== */
        initVisitSummary: function () {
            var self = this;
            var formHolders = $('.form-holder');
            if (!formHolders.length) return;

            var detectedForms = [];
            formHolders.each(function () {
                var holderId = $(this).attr('id') || '';
                var parts = holderId.split('~');
                var formdir = parts[0] || '';
                var instanceId = parseInt(parts[1] || '0', 10) || 0;

                if (formdir && formdir !== 'newGroupEncounter') {
                    detectedForms.push({
                        formdir: formdir,
                        instanceId: instanceId,
                        holder: $(this)
                    });
                }
            });

            if (!detectedForms.length) return;

            // Immediate hide using pre-rendered event data if available
            if (window.oeGroupCanvasHiddenFieldsMap) {
                detectedForms.forEach(function (item) {
                    if (window.oeGroupCanvasHiddenFieldsMap[item.formdir]) {
                        self.hideVisitSummaryFields(item.formdir, item.holder, window.oeGroupCanvasHiddenFieldsMap[item.formdir]);
                    }
                });
            }

            detectedForms.forEach(function (item) {
                self.loadVisitSummaryForm(item);
            });

            // Re-apply when accordions are expanded
            $(document).on('show.bs.collapse shown.bs.collapse', '.collapse', function () {
                var collapseElem = $(this);
                var holder = collapseElem.closest('.form-holder');
                if (holder.length && window.oeGroupCanvasHiddenFieldsMap) {
                    var holderId = holder.attr('id') || '';
                    var formdir = holderId.split('~')[0] || '';
                    if (formdir && window.oeGroupCanvasHiddenFieldsMap[formdir]) {
                        self.hideVisitSummaryFields(formdir, holder, window.oeGroupCanvasHiddenFieldsMap[formdir]);
                    }
                }
            });
        },

        loadVisitSummaryForm: function (item) {
            var self = this;
            var formdir = item.formdir;
            var instanceId = item.instanceId;
            var holder = item.holder;
            var apiUrl = this.moduleBaseUrl + '/public/api/get_config.php';

            $.ajax({
                url: apiUrl,
                type: 'GET',
                data: {
                    form_id: formdir,
                    pid: this.pid,
                    encounter: this.encounter
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        // Hide any fields configured as hidden
                        var hiddenList = res.hidden_fields_details || res.hidden_fields || [];
                        if (Array.isArray(hiddenList) && hiddenList.length > 0) {
                            self.hideVisitSummaryFields(formdir, holder, hiddenList);
                        }

                        if (Array.isArray(res.configs) && res.configs.length > 0) {
                            self.formConfigs[formdir] = res.configs;
                            self.injectVisitSummaryComponents(formdir, instanceId, holder, res.configs);
                        }
                    }
                },
                error: function (err) {
                    console.warn('Group Canvas: Failed to load configs for visit summary form ' + formdir, err);
                }
            });
        },

        hideVisitSummaryFields: function (formdir, holder, hiddenFields) {
            if (!holder || !holder.length || !Array.isArray(hiddenFields) || !hiddenFields.length) return;

            hiddenFields.forEach(function (item) {
                var fieldId = typeof item === 'object' ? (item.field_id || '') : String(item);
                var fieldTitle = typeof item === 'object' ? (item.title || '') : '';

                var targetPatterns = [];
                if (fieldTitle) {
                    targetPatterns.push(fieldTitle.trim().toLowerCase());
                }
                if (fieldId) {
                    targetPatterns.push(fieldId.trim().toLowerCase());
                    targetPatterns.push(fieldId.replace(/_/g, ' ').trim().toLowerCase());
                }

                holder.find('td.label_custom, td.label, th.label_custom').each(function () {
                    var labelElem = $(this);
                    var rawText = labelElem.text().replace(/[:\s]+$/, '').trim().toLowerCase();

                    var isMatch = targetPatterns.some(function (p) {
                        return p && (rawText === p || rawText.indexOf(p) === 0);
                    });

                    if (isMatch) {
                        labelElem.addClass('d-none').hide();

                        var nextTd = labelElem.next('td.data, td.text, td');
                        if (nextTd.length) {
                            nextTd.addClass('d-none').hide();
                        }

                        var tr = labelElem.closest('tr');
                        var visibleCells = tr.find('> td:visible, > th:visible');
                        var hasContent = false;
                        visibleCells.each(function () {
                            var cell = $(this);
                            if (!cell.hasClass('align-top') && !cell.hasClass('groupname') && cell.text().trim() !== '') {
                                hasContent = true;
                            }
                        });

                        if (!hasContent) {
                            tr.addClass('d-none').hide();
                        }
                    }
                });
            });
        },

        injectVisitSummaryComponents: function (formdir, instanceId, holder, configs) {
            var self = this;

            configs.forEach(function (cfg) {
                if (!cfg.has_image || !cfg.background_image_url) {
                    return;
                }

                var groupId = String(cfg.group_id);
                var btnLabel = cfg.button_label || 'Display Canvas';
                var hasDrawing = !!cfg.has_drawing;

                // 1. Locate the Group Header element in Encounter Summary
                var targetHeader = self.findSummaryGroupHeaderElement(holder, formdir, cfg);

                // 2. Inject Display Canvas Button directly next to the Group Header
                if (targetHeader && targetHeader.length) {
                    var btnKey = 'oe_summary_btn_' + formdir + '_' + groupId;
                    if (!targetHeader.find('#' + btnKey).length && !targetHeader.find('.oe-summary-canvas-wrapper[data-group="' + groupId + '"]').length) {
                        var btnClass = 'btn btn-sm ' + (hasDrawing ? 'btn-success has-saved-data' : 'btn-primary') + ' oe-group-canvas-btn oe-summary-group-canvas-btn';
                        var icon = hasDrawing ? 'fa-check-circle' : 'fa-image';
                        var badgeHtml = hasDrawing ? '<span class="oe-group-canvas-badge">Saved</span>' : '';

                        var wrapper = $(
                            '<span class="oe-group-canvas-wrapper oe-summary-canvas-wrapper ml-2" data-form="' + formdir + '" data-group="' + groupId + '">' +
                            '  <button type="button" class="' + btnClass + '" id="' + btnKey + '" data-form="' + formdir + '" data-group="' + groupId + '" title="View canvas diagram for ' + self.escapeHtml(btnLabel) + '">' +
                            '    <i class="fa ' + icon + ' mr-1"></i>' +
                            '    <span>' + self.escapeHtml(btnLabel) + '</span>' +
                            '    ' + badgeHtml +
                            '  </button>' +
                            '</span>'
                        );

                        wrapper.find('button').on('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            self.openModal({
                                form_id: formdir,
                                group_id: groupId,
                                form_instance_id: instanceId,
                                pid: self.pid,
                                encounter: self.encounter,
                                button_label: cfg.button_label || 'Display Canvas',
                                background_image_url: cfg.background_image_url,
                                canvas_width: cfg.canvas_width,
                                canvas_height: cfg.canvas_height,
                                has_drawing: cfg.has_drawing,
                                drawing_png: cfg.drawing_png,
                                readOnly: true
                            });
                        });

                        targetHeader.append(wrapper);
                    }
                }
            });
        },

        findSummaryGroupHeaderElement: function (holder, formdir, cfg) {
            var groupId = String(cfg.group_id || '').trim();
            var groupTitle = String(cfg.group_title || '').trim().toLowerCase();
            var groupSubtitle = String(cfg.group_subtitle || '').trim().toLowerCase();
            var target = null;

            var formDetail = holder.find('.form-detail');
            var searchScope = formDetail.length ? formDetail : holder;

            // 1. Check td.groupname, th.groupname, or .groupname matching groupTitle or groupSubtitle
            var groupNameCells = searchScope.find('td.groupname, th.groupname, .groupname');
            if (groupTitle && groupNameCells.length) {
                groupNameCells.each(function () {
                    if (target) return;
                    var cell = $(this);
                    var cellText = cell.clone().children('.oe-group-canvas-wrapper, .oe-summary-canvas-wrapper, button, span.oe-group-canvas-badge, .badge').remove().end().text().trim().toLowerCase();
                    if (cellText === groupTitle || cellText.indexOf(groupTitle) === 0 || (groupSubtitle && cellText.indexOf(groupSubtitle) === 0)) {
                        target = cell;
                    }
                });
            }

            if (target && target.length) return target;

            // 2. Check other group headers (legend, h4, h5, h6, .card-header, span.bold, .group-header)
            var otherHeaders = searchScope.find('.group-header, span.bold, label.bold, legend, h4, h5, h6, .card-header, .section-header');
            if (groupTitle && otherHeaders.length) {
                otherHeaders.each(function () {
                    if (target) return;
                    var el = $(this);
                    var elText = el.clone().children('.oe-group-canvas-wrapper, .oe-summary-canvas-wrapper, button, span.oe-group-canvas-badge, .badge').remove().end().text().trim().toLowerCase();
                    if (elText === groupTitle || elText.indexOf(groupTitle) === 0) {
                        target = el;
                    }
                });
            }

            if (target && target.length) return target;

            // 3. Check by form_cb_ input, div_grp, or data-group attributes
            var cb = searchScope.find('input[name="form_cb_grp-' + formdir + '-' + groupId + '"], input[name*="form_cb_"][name$="-' + groupId + '"], input[name="form_cb_lbf' + groupId + '"]');
            if (cb.length) {
                target = cb.closest('label, span.bold, .group-header');
                if (!target.length) target = cb.parent();
                if (target.length) return target;
            }

            var lbfDiv = searchScope.find('#div_grp-' + formdir + '-' + groupId + ', #div_lbf' + groupId + ', [id$="-' + groupId + '"]').first();
            if (lbfDiv.length) {
                target = lbfDiv.prev('span, label, h4, h5, .card-header, legend');
                if (!target.length) target = lbfDiv.find('.card-header, legend, h4, h5, .font-weight-bold').first();
                if (target.length) return target;
            }

            // 4. Match by index/sequence if group_seq or numeric groupId is available
            if (groupNameCells.length) {
                var idx = parseInt(groupId, 10) - 1;
                if (!isNaN(idx) && idx >= 0 && idx < groupNameCells.length) {
                    return groupNameCells.eq(idx);
                }
                if (groupNameCells.length === 1) {
                    return groupNameCells.first();
                }
            }

            // 5. Fallback to first groupname cell
            if (groupNameCells.length) {
                return groupNameCells.first();
            }

            return null;
        },

        /* ==========================================================================
           Element Finders for Layout Forms
           ========================================================================== */
        findGroupHeaderElement: function (formId, groupId) {
            var target = null;

            // 1. form_cb_grp-{formId}-{groupId}
            var cb1 = $('input[name="form_cb_grp-' + formId + '-' + groupId + '"]');
            if (cb1.length) {
                target = cb1.closest('label, span.bold, .group-header');
                if (!target.length) target = cb1.parent();
                if (target.length) return target;
            }

            // 2. form_cb_ with suffix -{groupId}
            var cb2 = $('input[name*="form_cb_"][name$="-' + groupId + '"]');
            if (cb2.length) {
                target = cb2.closest('label, span.bold, .group-header');
                if (!target.length) target = cb2.parent();
                if (target.length) return target;
            }

            // 3. form_cb_lbf{groupId}
            var cb3 = $('input[name="form_cb_lbf' + groupId + '"]');
            if (cb3.length) {
                target = cb3.closest('label, span.bold, .group-header');
                if (!target.length) target = cb3.parent();
                if (target.length) return target;
            }

            // 4. Div section previous header
            var lbfDiv = $('#div_grp-' + formId + '-' + groupId + ', #div_lbf' + groupId);
            if (lbfDiv.length) {
                target = lbfDiv.prev('span, label, h4, h5, .card-header, legend');
                if (target.length) return target;
            }

            // 5. Container heading
            var grpCont = $('#grp-' + formId + '-' + groupId + ', [id$="-' + groupId + '"]').first();
            if (grpCont.length) {
                target = grpCont.find('a[data-toggle="collapse"], .card-header, legend, h4, h5, .font-weight-bold').first();
                if (target.length) return target;
            }

            return target;
        },

        findGroupSectionElement: function (formId, groupId) {
            var div1 = $('#div_grp-' + formId + '-' + groupId);
            if (div1.length) return div1;

            var div2 = $('#div_lbf' + groupId);
            if (div2.length) return div2;

            var div3 = $('[id="div_grp-' + groupId + '"], [id$="-' + groupId + '"]').first();
            if (div3.length && div3.hasClass('section')) return div3;

            return null;
        },

        escapeHtml: function (str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        /* ==========================================================================
           Interactive Drawing Modal Engine
           ========================================================================== */
        ensureModalDOM: function () {
            if ($('#oe_canvas_modal_overlay').length) return;

            var modalHtml =
                '<div id="oe_canvas_modal_overlay" class="oe-canvas-modal-overlay">' +
                '  <div class="oe-canvas-modal-container">' +
                '    <div class="oe-canvas-modal-header">' +
                '      <div class="oe-canvas-modal-title" id="oe_canvas_modal_title">' +
                '        <i class="fa fa-palette text-primary"></i> <span id="oe_canvas_title_text">Annotate Diagram</span>' +
                '        <span id="oe_canvas_group_indicator" class="badge badge-light ml-2 font-weight-normal text-muted"></span>' +
                '      </div>' +
                '      <div class="d-flex align-items-center">' +
                '        <button type="button" class="btn btn-success btn-sm font-weight-bold mr-3 oe-canvas-top-save-btn" id="oe_canvas_top_save_btn" title="Save Drawing to Database (Ctrl+S)">' +
                '          <i class="fa fa-save mr-1"></i> Save' +
                '        </button>' +
                '        <button type="button" class="oe-canvas-modal-close" id="oe_canvas_close_btn" title="Close Modal">&times;</button>' +
                '      </div>' +
                '    </div>' +
                '    <div class="oe-canvas-toolbar">' +
                '      <!-- Drawing Tool Buttons -->' +
                '      <div class="oe-canvas-tool-group">' +
                '        <button type="button" class="oe-tool-btn active" data-tool="pen" title="Freehand Pen (Draw & Mark)"><i class="fa fa-pencil-alt"></i> Pen</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="highlighter" title="Highlighter (Semi-transparent)"><i class="fa fa-highlighter"></i> Highlight</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="arrow" title="Pointer Arrow"><i class="fa fa-long-arrow-alt-right"></i> Arrow</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="circle" title="Circle / Ellipse"><i class="far fa-circle"></i> Circle</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="rect" title="Rectangle / Box"><i class="far fa-square"></i> Box</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="line" title="Straight Line"><i class="fa fa-slash"></i> Line</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="text" title="Text Annotation (Click canvas to write)"><i class="fa fa-font"></i> Text</button>' +
                '        <button type="button" class="oe-tool-btn" data-tool="eraser" title="Eraser (Erase drawings)"><i class="fa fa-eraser"></i> Eraser</button>' +
                '      </div>' +
                '      <!-- Color Swatches -->' +
                '      <div class="oe-canvas-tool-group" id="oe_canvas_colors" title="Select Color">' +
                '        <span class="oe-color-swatch active" data-color="#d9534f" style="background:#d9534f;" title="Red"></span>' +
                '        <span class="oe-color-swatch" data-color="#0275d8" style="background:#0275d8;" title="Blue"></span>' +
                '        <span class="oe-color-swatch" data-color="#5cb85c" style="background:#5cb85c;" title="Green"></span>' +
                '        <span class="oe-color-swatch" data-color="#f0ad4e" style="background:#f0ad4e;" title="Orange"></span>' +
                '        <span class="oe-color-swatch" data-color="#6f42c1" style="background:#6f42c1;" title="Purple"></span>' +
                '        <span class="oe-color-swatch" data-color="#17a2b8" style="background:#17a2b8;" title="Cyan"></span>' +
                '        <span class="oe-color-swatch" data-color="#212529" style="background:#212529;" title="Black"></span>' +
                '        <span class="oe-color-swatch" data-color="#ffffff" style="background:#ffffff; border-color:#ced4da;" title="White"></span>' +
                '      </div>' +
                '      <!-- Stroke Width Selector -->' +
                '      <div class="oe-canvas-tool-group" title="Line / Marker Size">' +
                '        <span class="small font-weight-bold text-muted mr-1"><i class="fa fa-paint-brush"></i> Size:</span>' +
                '        <select id="oe_canvas_stroke_size" class="form-control form-control-sm oe-toolbar-select">' +
                '          <option value="2">Fine (2px)</option>' +
                '          <option value="4" selected>Medium (4px)</option>' +
                '          <option value="7">Thick (7px)</option>' +
                '          <option value="12">Bold (12px)</option>' +
                '        </select>' +
                '      </div>' +
                '      <!-- Text Font Size Selector -->' +
                '      <div class="oe-canvas-tool-group" id="oe_canvas_text_size_group" title="Text Font Size">' +
                '        <span class="small font-weight-bold text-muted mr-1"><i class="fa fa-text-height"></i> Text:</span>' +
                '        <select id="oe_canvas_text_size" class="form-control form-control-sm oe-toolbar-select">' +
                '          <option value="14">Small (14px)</option>' +
                '          <option value="18" selected>Medium (18px)</option>' +
                '          <option value="24">Large (24px)</option>' +
                '          <option value="30">XL (30px)</option>' +
                '        </select>' +
                '      </div>' +
                '      <!-- History Stack & Save Controls -->' +
                '      <div class="oe-canvas-tool-group ml-auto">' +
                '        <button type="button" class="btn btn-sm btn-secondary py-1 px-2" id="oe_canvas_undo_btn" title="Undo (Ctrl+Z)"><i class="fa fa-undo"></i> Undo</button>' +
                '        <button type="button" class="btn btn-sm btn-secondary py-1 px-2" id="oe_canvas_redo_btn" title="Redo (Ctrl+Y)"><i class="fa fa-redo"></i> Redo</button>' +
                '        <button type="button" class="btn btn-sm btn-danger py-1 px-2 mr-2" id="oe_canvas_clear_btn" title="Clear Canvas Annotations"><i class="fa fa-trash"></i> Clear</button>' +
                '        <button type="button" class="btn btn-sm btn-success font-weight-bold py-1 px-3 oe-canvas-toolbar-save-btn" id="oe_canvas_toolbar_save_btn" title="Save (Ctrl+S)">' +
                '          <i class="fa fa-save mr-1"></i> Save' +
                '        </button>' +
                '      </div>' +
                '    </div>' +
                '    <div class="oe-canvas-modal-body">' +
                '      <div class="oe-canvas-viewport" id="oe_canvas_container"></div>' +
                '    </div>' +
                '    <div class="oe-canvas-modal-footer">' +
                '      <div id="oe_canvas_status" class="oe-canvas-status-msg text-muted">Ready</div>' +
                '      <div>' +
                '        <button type="button" class="btn btn-secondary btn-sm mr-2" id="oe_canvas_cancel_btn">Close</button>' +
                '        <button type="button" class="btn btn-success btn-sm font-weight-bold" id="oe_canvas_save_btn">' +
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

            $('#oe_canvas_close_btn, #oe_canvas_cancel_btn').on('click', function () {
                $('#oe_canvas_modal_overlay').removeClass('active');
            });

            $('.oe-tool-btn').on('click', function () {
                $('.oe-tool-btn').removeClass('active');
                $(this).addClass('active');
                var tool = $(this).data('tool');
                if (self.activeDrawer) {
                    self.activeDrawer.setTool(tool);
                }
            });

            $('.oe-color-swatch').on('click', function () {
                $('.oe-color-swatch').removeClass('active');
                $(this).addClass('active');
                var color = $(this).data('color');
                if (self.activeDrawer) {
                    self.activeDrawer.setColor(color);
                }
            });

            $('#oe_canvas_stroke_size').on('change', function () {
                var size = $(this).val();
                if (self.activeDrawer) {
                    self.activeDrawer.setStrokeWidth(size);
                }
            });

            $('#oe_canvas_text_size').on('change', function () {
                var size = $(this).val();
                if (self.activeDrawer) {
                    self.activeDrawer.setFontSize(size);
                }
            });

            $('#oe_canvas_undo_btn').on('click', function () {
                if (self.activeDrawer) self.activeDrawer.undo();
            });
            $('#oe_canvas_redo_btn').on('click', function () {
                if (self.activeDrawer) self.activeDrawer.redo();
            });
            $('#oe_canvas_clear_btn').on('click', function () {
                if (self.activeDrawer) self.activeDrawer.clear();
            });

            $('#oe_canvas_save_btn, #oe_canvas_top_save_btn, #oe_canvas_toolbar_save_btn').on('click', function () {
                self.saveCurrentDrawing();
            });

            $(document).on('keydown', function (e) {
                if (!$('#oe_canvas_modal_overlay').hasClass('active')) return;

                if (e.key === 'Escape') {
                    $('#oe_canvas_modal_overlay').removeClass('active');
                } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    self.saveCurrentDrawing();
                } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    if (self.activeDrawer) self.activeDrawer.undo();
                } else if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) {
                    e.preventDefault();
                    if (self.activeDrawer) self.activeDrawer.redo();
                }
            });
        },

        openModal: function (context) {
            var self = this;
            this.currentModalContext = context;

            var formId = context.form_id;
            var groupId = context.group_id;
            var pid = context.pid || this.pid;
            var encounter = context.encounter || this.encounter;
            var instanceId = context.form_instance_id || this.formInstanceId || 0;
            var isReadOnly = !!context.readOnly;

            var titleText = context.button_label || 'Canvas Diagram';
            if (isReadOnly) {
                titleText += ' (View)';
            }
            $('#oe_canvas_title_text').text(titleText);
            $('#oe_canvas_group_indicator').text('Group ' + groupId + ' (' + formId + ')');
            $('#oe_canvas_status').text('Loading canvas diagram...').removeClass('text-success text-danger').addClass('text-muted');

            if (isReadOnly) {
                $('#oe_canvas_modal_overlay').addClass('oe-canvas-readonly');
            } else {
                $('#oe_canvas_modal_overlay').removeClass('oe-canvas-readonly');
            }

            $('#oe_canvas_modal_overlay').addClass('active');

            var container = $('#oe_canvas_container');
            container.empty();

            if (isReadOnly) {
                this.activeDrawer = null;
                var initialImg = (context.has_drawing && context.drawing_png) ? context.drawing_png : context.background_image_url;

                container.html(
                    '<div class="oe-canvas-view-wrapper">' +
                    '  <img src="' + initialImg + '" class="oe-canvas-view-image" id="oe_canvas_view_image" alt="' + self.escapeHtml(context.button_label || 'Canvas Diagram') + '" />' +
                    '</div>'
                );

                var getApiUrl = this.moduleBaseUrl + '/public/api/get_drawing.php';
                $.ajax({
                    url: getApiUrl,
                    type: 'GET',
                    data: {
                        pid: pid,
                        encounter: encounter,
                        form_id: formId,
                        group_id: groupId,
                        form_instance_id: instanceId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res && res.success && res.drawing && res.drawing.drawing_png) {
                            $('#oe_canvas_view_image').attr('src', res.drawing.drawing_png);
                            $('#oe_canvas_status').text('Saved canvas drawing displayed (View Only)').addClass('text-success');
                        } else {
                            $('#oe_canvas_status').text('Diagram template displayed (No annotations recorded)').addClass('text-muted');
                        }
                    },
                    error: function () {
                        $('#oe_canvas_status').text('Diagram displayed (View Only)').addClass('text-muted');
                    }
                });
                return;
            }

            var width = context.canvas_width || 800;
            var height = context.canvas_height || 600;
            var bgUrl = context.background_image_url || '';

            this.activeDrawer = new GroupCanvasDrawer('oe_canvas_container', {
                width: width,
                height: height,
                backgroundImage: bgUrl
            });

            var activeTool = $('.oe-tool-btn.active').data('tool') || 'pen';
            var activeColor = $('.oe-color-swatch.active').data('color') || '#d9534f';
            var strokeSize = $('#oe_canvas_stroke_size').val() || 4;
            var textSize = $('#oe_canvas_text_size').val() || 18;

            this.activeDrawer.setTool(activeTool);
            this.activeDrawer.setColor(activeColor);
            this.activeDrawer.setStrokeWidth(strokeSize);
            this.activeDrawer.setFontSize(textSize);

            // Fetch drawing vector data from database
            var getApiUrl = this.moduleBaseUrl + '/public/api/get_drawing.php';
            $.ajax({
                url: getApiUrl,
                type: 'GET',
                data: {
                    pid: pid,
                    encounter: encounter,
                    form_id: formId,
                    group_id: groupId,
                    form_instance_id: instanceId
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success && res.drawing) {
                        if (res.pid && (!self.pid || self.pid <= 0)) {
                            self.pid = parseInt(res.pid, 10) || self.pid;
                        }
                        if (res.encounter && (!self.encounter || self.encounter <= 0)) {
                            self.encounter = parseInt(res.encounter, 10) || self.encounter;
                        }
                        if (res.drawing.drawing_data) {
                            self.activeDrawer.loadJSON(res.drawing.drawing_data);
                            $('#oe_canvas_status').text('Saved drawing loaded. You can modify or add annotations.').addClass('text-success');
                        } else if (res.drawing.drawing_png) {
                            self.activeDrawer.loadDrawingImage(res.drawing.drawing_png);
                            $('#oe_canvas_status').text('Saved drawing loaded. You can modify or add annotations.').addClass('text-success');
                        } else {
                            $('#oe_canvas_status').text('Ready for new markings & text annotations.').addClass('text-muted');
                        }
                    } else if (context.drawing_png) {
                        self.activeDrawer.loadDrawingImage(context.drawing_png);
                        $('#oe_canvas_status').text('Saved drawing loaded. You can modify or add annotations.').addClass('text-success');
                    } else {
                        $('#oe_canvas_status').text('Ready for new markings & text annotations.').addClass('text-muted');
                    }
                },
                error: function () {
                    if (context.drawing_png) {
                        self.activeDrawer.loadDrawingImage(context.drawing_png);
                        $('#oe_canvas_status').text('Saved drawing loaded.').addClass('text-success');
                    } else {
                        $('#oe_canvas_status').text('Ready.').addClass('text-muted');
                    }
                }
            });
        },

        saveCurrentDrawing: function () {
            if (!this.activeDrawer || !this.currentModalContext) return;

            var self = this;
            var ctx = this.currentModalContext;
            var saveBtns = $('#oe_canvas_save_btn, #oe_canvas_top_save_btn, #oe_canvas_toolbar_save_btn');
            var statusMsg = $('#oe_canvas_status');

            saveBtns.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
            statusMsg.text('Saving drawing to database...').removeClass('text-success text-danger').addClass('text-muted');

            var exportedPng = this.activeDrawer.getPNG();
            var exportedJson = this.activeDrawer.getJSON();

            var curPid = ctx.pid || self.pid || parseInt($('input[name="pid"], input[name="form_pid"], input[name="patientid"], input[name="pId"]').val(), 10) || (window.top && window.top.pid ? window.top.pid : 0) || (window.parent && window.parent.pid ? window.parent.pid : 0) || 0;
            var curEnc = ctx.encounter || self.encounter || parseInt($('input[name="encounter"], input[name="form_encounter"], input[name="visitid"]').val(), 10) || (window.top && window.top.encounter ? window.top.encounter : 0) || (window.parent && window.parent.encounter ? window.parent.encounter : 0) || 0;

            var payload = {
                pid: curPid,
                encounter: curEnc,
                form_id: ctx.form_id,
                group_id: ctx.group_id,
                form_instance_id: ctx.form_instance_id || self.formInstanceId || 0,
                drawing_data: exportedJson,
                drawing_png: exportedPng,
                csrf_token_form: self.csrfToken
            };

            var saveApiUrl = this.moduleBaseUrl + '/public/api/save_drawing.php';

            $.ajax({
                url: saveApiUrl,
                type: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                dataType: 'json',
                success: function (res) {
                    saveBtns.prop('disabled', false);
                    saveBtns.html('<i class="fa fa-save mr-1"></i> Save');

                    if (res && res.success) {
                        statusMsg.text('Drawing saved successfully!').removeClass('text-muted').addClass('text-success');

                        // 1. Update single-form and visit summary header button states
                        var allBtns = $('button.oe-group-canvas-btn[data-form="' + ctx.form_id + '"][data-group="' + ctx.group_id + '"], button.oe-group-canvas-btn[data-group="' + ctx.group_id + '"], #oe_summary_btn_' + ctx.form_id + '_' + ctx.group_id);
                        allBtns.removeClass('btn-primary').addClass('btn-success has-saved-data');
                        allBtns.find('i').removeClass('fa-paint-brush fa-palette fa-image').addClass('fa-check-circle');
                        allBtns.each(function () {
                            if (!$(this).find('.oe-group-canvas-badge').length) {
                                $(this).append('<span class="oe-group-canvas-badge">Saved</span>');
                            }
                        });

                        // 2. Update displayed images on the page
                        var displayImg = $('img#oe_group_display_img_' + ctx.form_id + '_' + ctx.group_id + ', img#oe_group_display_img_' + ctx.group_id + ', img.oe-summary-display-img[data-form="' + ctx.form_id + '"][data-group="' + ctx.group_id + '"]');
                        if (displayImg.length) {
                            displayImg.attr('src', exportedPng);
                        }

                        // 3. Update card badge
                        var cardBadge = $('#oe_card_badge_' + ctx.form_id + '_' + ctx.group_id + ', #oe_card_badge_' + ctx.group_id);
                        if (cardBadge.length) {
                            cardBadge.html('<span class="badge badge-success oe-card-badge"><i class="fa fa-check-circle mr-1"></i> Annotated & Saved</span>');
                        }

                        // 5. Update hidden form inputs if on an active layout form
                        var hiddenInput = $('#oe_canvas_png_input_' + ctx.form_id + '_' + ctx.group_id);
                        if (hiddenInput.length) {
                            hiddenInput.val(exportedPng);
                        }

                        // 6. Update local config cache
                        if (self.formConfigs[ctx.form_id]) {
                            self.formConfigs[ctx.form_id].forEach(function (c) {
                                if (c.group_id === ctx.group_id) {
                                    c.has_drawing = true;
                                    c.drawing_png = exportedPng;
                                }
                            });
                        }

                        setTimeout(function () {
                            $('#oe_canvas_modal_overlay').removeClass('active');
                        }, 900);
                    } else {
                        statusMsg.text('Error: ' + (res.message || 'Save failed')).removeClass('text-muted').addClass('text-danger');
                    }
                },
                error: function () {
                    saveBtns.prop('disabled', false);
                    saveBtns.html('<i class="fa fa-save mr-1"></i> Save');
                    statusMsg.text('Network or server error while saving').removeClass('text-muted').addClass('text-danger');
                }
            });
        }
    };

    GroupCanvasManager.init();

})(jQuery);
