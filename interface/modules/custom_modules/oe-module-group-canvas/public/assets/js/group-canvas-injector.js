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

                if (self.isVisitSummary) {
                    self.initVisitSummary();
                } else if (self.formId) {
                    self.loadSingleFormConfigs(self.formId);
                } else {
                    // Fallback: check if any form-holders exist on the page
                    if ($('.form-holder, #partable').length) {
                        self.isVisitSummary = true;
                        self.initVisitSummary();
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
                    pid: this.pid,
                    encounter: this.encounter
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success && Array.isArray(res.configs)) {
                        self.formConfigs[formId] = res.configs;
                        self.injectSingleFormComponents(formId, res.configs);
                        self.bindFormSubmitSync(formId);
                    }
                },
                error: function (err) {
                    console.warn('Group Canvas: Failed to load form configurations', err);
                }
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
                    if (!targetHeader.find('.oe-group-canvas-wrapper[data-group="' + groupId + '"]').length) {
                        var btnClass = 'oe-group-canvas-btn' + (hasDrawing ? ' has-saved-data' : '');
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

                        targetHeader.append(wrapper);
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
                        '<div class="oe-group-canvas-image-card" id="' + cardId + '" data-form="' + formId + '" data-group="' + groupId + '">' +
                        '  <div class="oe-card-top-bar">' +
                        '    <div class="oe-card-title">' +
                        '      <i class="fa fa-palette text-primary mr-1"></i>' +
                        '      <span>' + self.escapeHtml(btnLabel) + '</span>' +
                        '    </div>' +
                        '    <div class="oe-card-meta">' +
                        '      <span id="oe_card_badge_' + formId + '_' + groupId + '">' + cardStatusBadge + '</span>' +
                        '      <button type="button" class="btn btn-sm btn-primary ml-2 oe-card-open-canvas-btn" data-form="' + formId + '" data-group="' + groupId + '">' +
                        '        <i class="fa fa-pen-fancy mr-1"></i> Canvas' +
                        '      </button>' +
                        '    </div>' +
                        '  </div>' +
                        '  <div class="oe-card-image-wrapper" title="Click to open canvas and annotate">' +
                        '    <img src="' + displayImgUrl + '" class="oe-group-display-img" id="oe_group_display_img_' + formId + '_' + groupId + '" alt="' + self.escapeHtml(btnLabel) + '" />' +
                        '    <div class="oe-card-hover-overlay">' +
                        '      <div class="oe-card-overlay-content">' +
                        '        <i class="fa fa-expand-arrows-alt fa-2x mb-1"></i>' +
                        '        <span class="font-weight-bold">Click to Open Canvas & Annotate</span>' +
                        '      </div>' +
                        '    </div>' +
                        '  </div>' +
                        '</div>'
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

            detectedForms.forEach(function (item) {
                self.loadVisitSummaryForm(item);
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
                    if (res && res.success && Array.isArray(res.configs) && res.configs.length > 0) {
                        self.formConfigs[formdir] = res.configs;
                        self.injectVisitSummaryComponents(formdir, instanceId, holder, res.configs);
                    }
                },
                error: function (err) {
                    console.warn('Group Canvas: Failed to load configs for visit summary form ' + formdir, err);
                }
            });
        },

        injectVisitSummaryComponents: function (formdir, instanceId, holder, configs) {
            var self = this;
            var headerControls = holder.find('.form_header_controls');
            var formDetail = holder.find('.form-detail');

            configs.forEach(function (cfg) {
                if (!cfg.has_image || !cfg.background_image_url) {
                    return;
                }

                var groupId = cfg.group_id;
                var btnLabel = cfg.button_label || 'Canvas Diagram';
                var hasDrawing = cfg.has_drawing;
                var displayImgUrl = (hasDrawing && cfg.drawing_png) ? cfg.drawing_png : cfg.background_image_url;

                // 1. Inject Canvas Button into the Form Header Controls
                if (headerControls.length) {
                    var btnKey = 'oe_vs_btn_' + formdir + '_' + groupId;
                    if (!$('#' + btnKey).length) {
                        var iconClass = hasDrawing ? 'fa-check-circle text-success' : 'fa-palette text-primary';
                        var badgeHtml = hasDrawing ? '<span class="badge badge-success ml-1">Annotated</span>' : '';

                        var vsBtn = $(
                            '<a href="#" class="btn btn-text btn-sm oe-visit-summary-canvas-btn mr-1" id="' + btnKey + '" ' +
                            '   title="View and Annotate ' + self.escapeHtml(btnLabel) + '">' +
                            '  <i class="fa ' + iconClass + ' mr-1"></i>' +
                            '  <span>' + self.escapeHtml(btnLabel) + '</span> ' +
                            '  ' + badgeHtml +
                            '</a>'
                        );

                        vsBtn.on('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            self.openModal({
                                form_id: formdir,
                                group_id: groupId,
                                form_instance_id: instanceId,
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

                        headerControls.prepend(vsBtn);
                    }
                }

                // 2. Inject Display Card inside the Form Detail / Report view
                if (formDetail.length) {
                    var cardKey = 'oe_vs_card_' + formdir + '_' + groupId;
                    if (!$('#' + cardKey).length) {
                        var cardBadge = hasDrawing
                            ? '<span class="badge badge-success oe-card-badge"><i class="fa fa-check-circle mr-1"></i> Patient Annotation Saved</span>'
                            : '<span class="badge badge-secondary oe-card-badge"><i class="fa fa-image mr-1"></i> Diagram Template</span>';

                        var vsCard = $(
                            '<div class="oe-group-canvas-image-card oe-visit-summary-card" id="' + cardKey + '" data-form="' + formdir + '" data-group="' + groupId + '">' +
                            '  <div class="oe-card-top-bar">' +
                            '    <div class="oe-card-title">' +
                            '      <i class="fa fa-palette text-primary mr-1"></i>' +
                            '      <span>' + self.escapeHtml(btnLabel) + '</span>' +
                            '    </div>' +
                            '    <div class="oe-card-meta">' +
                            '      <span id="oe_card_badge_' + formdir + '_' + groupId + '">' + cardBadge + '</span>' +
                            '      <button type="button" class="btn btn-sm btn-primary ml-2 oe-card-open-canvas-btn">' +
                            '        <i class="fa fa-expand-arrows-alt mr-1"></i> View / Annotate' +
                            '      </button>' +
                            '    </div>' +
                            '  </div>' +
                            '  <div class="oe-card-image-wrapper" title="Click to view full canvas in popup">' +
                            '    <img src="' + displayImgUrl + '" class="oe-group-display-img" id="oe_group_display_img_' + formdir + '_' + groupId + '" alt="' + self.escapeHtml(btnLabel) + '" />' +
                            '    <div class="oe-card-hover-overlay">' +
                            '      <div class="oe-card-overlay-content">' +
                            '        <i class="fa fa-expand fa-2x mb-1"></i>' +
                            '        <span class="font-weight-bold">Click to Open & View Canvas</span>' +
                            '      </div>' +
                            '    </div>' +
                            '  </div>' +
                            '</div>'
                        );

                        vsCard.on('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            self.openModal({
                                form_id: formdir,
                                group_id: groupId,
                                form_instance_id: instanceId,
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

                        formDetail.first().prepend(vsCard);
                    }
                }
            });
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
                '        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="oe_canvas_undo_btn" title="Undo (Ctrl+Z)"><i class="fa fa-undo"></i> Undo</button>' +
                '        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="oe_canvas_redo_btn" title="Redo (Ctrl+Y)"><i class="fa fa-redo"></i> Redo</button>' +
                '        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 mr-2" id="oe_canvas_clear_btn" title="Clear Canvas Annotations"><i class="fa fa-trash"></i> Clear</button>' +
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

            $('#oe_canvas_title_text').text(context.button_label || 'Annotate Diagram');
            $('#oe_canvas_group_indicator').text('Group ' + groupId + ' (' + formId + ')');
            $('#oe_canvas_status').text('Loading drawing...').removeClass('text-success text-danger').addClass('text-muted');
            $('#oe_canvas_modal_overlay').addClass('active');

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
                    if (res && res.success && res.drawing && res.drawing.drawing_data) {
                        self.activeDrawer.loadJSON(res.drawing.drawing_data);
                        $('#oe_canvas_status').text('Saved drawing loaded. You can modify or add annotations.').addClass('text-success');
                    } else {
                        $('#oe_canvas_status').text('Ready for new markings & text annotations.').addClass('text-muted');
                    }
                },
                error: function () {
                    $('#oe_canvas_status').text('Ready.').addClass('text-muted');
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

                        // 1. Update single-form header button state
                        var btn = $('button.oe-group-canvas-btn[data-form="' + ctx.form_id + '"][data-group="' + ctx.group_id + '"], button.oe-group-canvas-btn[data-group="' + ctx.group_id + '"]');
                        btn.addClass('has-saved-data');
                        btn.find('i').removeClass('fa-paint-brush').addClass('fa-check-circle');
                        if (!btn.find('.oe-group-canvas-badge').length) {
                            btn.append('<span class="oe-group-canvas-badge">Saved</span>');
                        }

                        // 2. Update Visit Summary button state
                        var vsBtn = $('#oe_vs_btn_' + ctx.form_id + '_' + ctx.group_id);
                        if (vsBtn.length) {
                            vsBtn.find('i').removeClass('fa-palette text-primary').addClass('fa-check-circle text-success');
                            if (!vsBtn.find('.badge-success').length) {
                                vsBtn.append('<span class="badge badge-success ml-1">Annotated</span>');
                            }
                        }

                        // 3. Update displayed images on the page
                        var displayImg = $('#oe_group_display_img_' + ctx.form_id + '_' + ctx.group_id + ', #oe_group_display_img_' + ctx.group_id);
                        if (displayImg.length) {
                            displayImg.attr('src', exportedPng);
                        }

                        // 4. Update card badge
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
