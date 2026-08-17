/**
 * OpenEMR Practice Employer Autocomplete & Auto-population
 * Module: oe-module-employer
 */
(function ($) {
    'use strict';

    function getSearchUrl() {
        if (typeof window.oe_employer_search_url !== 'undefined' && window.oe_employer_search_url) {
            return window.oe_employer_search_url;
        }

        let base = '';
        try {
            if (typeof top !== 'undefined' && top && typeof top.webroot_url !== 'undefined' && top.webroot_url) {
                base = top.webroot_url;
            } else if (typeof window.webroot_url !== 'undefined' && window.webroot_url) {
                base = window.webroot_url;
            } else if (typeof top !== 'undefined' && top && typeof top.web_root !== 'undefined' && top.web_root) {
                base = top.web_root;
            } else if (typeof window.web_root !== 'undefined' && window.web_root) {
                base = window.web_root;
            }
        } catch (e) {
            // cross-origin safety
        }

        if (!base) {
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                const src = scripts[i].getAttribute('src') || scripts[i].src || '';
                const idx = src.indexOf('/interface/modules/custom_modules/oe-module-employer');
                if (idx !== -1) {
                    base = src.substring(0, idx);
                    break;
                }
            }
        }

        if (base && base.endsWith('/')) {
            base = base.substring(0, base.length - 1);
        }

        return (base || '') + '/interface/modules/custom_modules/oe-module-employer/public/ajax_search_employers.php';
    }

    function setFieldValue($field, value) {
        if (!$field || $field.length === 0 || value === undefined || value === null) {
            return;
        }

        const valStr = String(value).trim();

        $field.each(function () {
            const $el = $(this);
            if ($el.is('select')) {
                let matched = false;
                $el.val(valStr);
                if ($el.val() === valStr) {
                    matched = true;
                } else {
                    $el.find('option').each(function () {
                        const optVal = $(this).val();
                        const optText = $(this).text().trim();
                        if (optVal.toLowerCase() === valStr.toLowerCase() || optText.toLowerCase() === valStr.toLowerCase()) {
                            $el.val(optVal);
                            matched = true;
                            return false;
                        }
                    });
                }
                if (!matched && valStr !== '') {
                    $el.append(new Option(valStr, valStr, true, true));
                    $el.val(valStr);
                }
            } else {
                $el.val(valStr);
            }

            try {
                this.dispatchEvent(new Event('input', { bubbles: true }));
                this.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {}

            $el.trigger('input').trigger('change');
            $el.addClass('oe-employer-field-highlight');
            setTimeout(function () {
                $el.removeClass('oe-employer-field-highlight');
            }, 2000);
        });
    }

    function populateEmployerData($nameInput, emp) {
        if (!emp) return;

        // 1. Populate employer name
        if ($nameInput && $nameInput.length > 0) {
            setFieldValue($nameInput, emp.name);
        } else {
            setFieldValue($('#form_em_name, input[name="form_em_name"], #em_name, input[name="em_name"]'), emp.name);
        }

        // 2. Populate employer address fields
        setFieldValue($('#form_em_street, input[name="form_em_street"], #em_street, input[name="em_street"]'), emp.street);
        setFieldValue($('#form_em_street_line_2, input[name="form_em_street_line_2"], #em_street_line_2, input[name="em_street_line_2"]'), emp.street_line_2);
        setFieldValue($('#form_em_city, input[name="form_em_city"], #em_city, input[name="em_city"]'), emp.city);
        setFieldValue($('#form_em_state, select[name="form_em_state"], input[name="form_em_state"], #em_state, select[name="em_state"]'), emp.state);
        setFieldValue($('#form_em_postal_code, input[name="form_em_postal_code"], #em_postal_code, input[name="em_postal_code"]'), emp.postal_code);
        setFieldValue($('#form_em_country, select[name="form_em_country"], input[name="form_em_country"], #em_country, select[name="em_country"]'), emp.country);

        // 3. Populate business phone if available
        if (emp.phone) {
            setFieldValue($('#form_em_phone, input[name="form_em_phone"], #em_phone, #form_phone_biz, input[name="form_phone_biz"]'), emp.phone);
        }
    }

    function initEmployerAutocomplete() {
        const nameFieldSelectors = [
            '#form_em_name',
            'input[name="form_em_name"]',
            '#em_name',
            'input[name="em_name"]'
        ];

        let $nameInput = null;
        for (let i = 0; i < nameFieldSelectors.length; i++) {
            const $candidate = $(nameFieldSelectors[i]);
            if ($candidate.length > 0) {
                $nameInput = $candidate;
                break;
            }
        }

        if (!$nameInput || $nameInput.length === 0) {
            return;
        }

        if ($nameInput.data('oe-emp-autocomplete-init')) {
            return;
        }

        $nameInput.data('oe-emp-autocomplete-init', true);
        $nameInput.attr('autocomplete', 'off');

        // Wrap input in a relative container so dropdown is 100% anchored to the input box
        if (!$nameInput.parent().hasClass('oe-emp-autocomplete-wrapper')) {
            $nameInput.wrap('<div class="oe-emp-autocomplete-wrapper" style="position: relative; display: block; width: 100%;"></div>');
        }

        const searchUrl = getSearchUrl();

        // Create Dropdown Container anchored directly inside the wrapper
        const $dropdown = $('<ul class="oe-employer-autocomplete-dropdown"></ul>');
        $dropdown.css({
            'position': 'absolute',
            'top': '100%',
            'left': '0',
            'width': '100%',
            'min-width': '280px',
            'max-height': '220px',
            'overflow-y': 'auto',
            'background-color': '#ffffff',
            'border': '1px solid #cbd5e0',
            'border-radius': '0.375rem',
            'box-shadow': '0 10px 25px rgba(0, 0, 0, 0.15)',
            'margin': '2px 0 0 0',
            'padding': '0',
            'list-style': 'none',
            'z-index': '99999',
            'display': 'none',
            'font-family': 'inherit',
            'font-size': '0.875rem'
        });

        $nameInput.parent().append($dropdown);

        let activeIndex = -1;
        let searchTimeout = null;
        let currentResults = [];

        function renderDropdown(items) {
            $dropdown.empty();
            currentResults = items || [];
            activeIndex = -1;

            if (currentResults.length === 0) {
                $dropdown.hide();
                return;
            }

            $.each(currentResults, function (idx, item) {
                const addressDetails = [item.street, item.city, item.state, item.postal_code].filter(Boolean).join(', ');
                const $li = $('<li class="oe-employer-autocomplete-item"></li>');

                $li.css({
                    'padding': '8px 12px',
                    'cursor': 'pointer',
                    'border-bottom': '1px solid #edf2f7',
                    'background-color': '#ffffff',
                    'list-style': 'none',
                    'text-align': 'left',
                    'display': 'block',
                    'transition': 'background-color 0.15s ease'
                });

                let html = '<div style="font-weight: 600; color: #2d3748; display: flex; justify-content: space-between; align-items: center;">' +
                           '<span>' + $('<div>').text(item.name).html() + '</span>';

                if (item.phone) {
                    html += '<span style="font-size: 0.75rem; background-color: #ebf8ff; color: #2b6cb0; padding: 2px 6px; border-radius: 4px; font-weight: 500;">' +
                            '<i class="fa fa-phone fa-xs mr-1"></i>' + $('<div>').text(item.phone).html() + '</span>';
                }
                html += '</div>';

                if (addressDetails) {
                    html += '<div style="font-size: 0.78rem; color: #718096; margin-top: 3px;">' +
                            '<i class="fa fa-map-marker-alt fa-xs mr-1"></i>' + $('<div>').text(addressDetails).html() + '</div>';
                }

                $li.html(html);

                $li.hover(
                    function () {
                        $(this).css({ 'background-color': '#f7fafc', 'color': '#2b6cb0' });
                    },
                    function () {
                        if (!$(this).hasClass('active')) {
                            $(this).css({ 'background-color': '#ffffff', 'color': '#2d3748' });
                        }
                    }
                );

                $li.on('mousedown click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    populateEmployerData($nameInput, item);
                    $dropdown.hide();
                });

                $dropdown.append($li);
            });

            $dropdown.show();
        }

        function doSearch(term) {
            $.ajax({
                url: searchUrl,
                type: 'GET',
                dataType: 'json',
                data: { term: term },
                success: function (data) {
                    if (Array.isArray(data) && data.length > 0) {
                        renderDropdown(data);
                    } else {
                        $dropdown.hide();
                    }
                },
                error: function (xhr, status, error) {
                    console.warn('Employer Autocomplete search failed:', searchUrl, error);
                    $dropdown.hide();
                }
            });
        }

        $nameInput.on('input keyup focus', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === 'Escape') {
                return;
            }
            const val = $(this).val().trim();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                doSearch(val);
            }, 100);
        });

        $nameInput.on('keydown', function (e) {
            if (!$dropdown.is(':visible')) {
                if (e.key === 'ArrowDown' || e.key === 'Down') {
                    doSearch($nameInput.val().trim());
                }
                return;
            }

            const $items = $dropdown.find('.oe-employer-autocomplete-item');
            if ($items.length === 0) return;

            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % $items.length;
                $items.removeClass('active').css({ 'background-color': '#ffffff' });
                $items.eq(activeIndex).addClass('active').css({ 'background-color': '#ebf8ff' });
                scrollIntoView($items.eq(activeIndex));
            } else if (e.key === 'ArrowUp' || e.key === 'Up') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + $items.length) % $items.length;
                $items.removeClass('active').css({ 'background-color': '#ffffff' });
                $items.eq(activeIndex).addClass('active').css({ 'background-color': '#ebf8ff' });
                scrollIntoView($items.eq(activeIndex));
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && activeIndex < currentResults.length) {
                    e.preventDefault();
                    e.stopPropagation();
                    populateEmployerData($nameInput, currentResults[activeIndex]);
                    $dropdown.hide();
                }
            } else if (e.key === 'Escape' || e.key === 'Esc') {
                $dropdown.hide();
            }
        });

        function scrollIntoView($item) {
            if (!$item || $item.length === 0) return;
            const containerTop = $dropdown.scrollTop();
            const containerBottom = containerTop + $dropdown.height();
            const elemTop = $item.position().top + containerTop;
            const elemBottom = elemTop + $item.outerHeight();

            if (elemTop < containerTop) {
                $dropdown.scrollTop(elemTop);
            } else if (elemBottom > containerBottom) {
                $dropdown.scrollTop(elemBottom - $dropdown.height());
            }
        }

        $nameInput.on('blur', function () {
            setTimeout(function () {
                $dropdown.hide();
            }, 250);
        });
    }

    // Initialize on document ready and re-check when layout tabs are clicked
    $(function () {
        initEmployerAutocomplete();

        $(document).on('click', '.tabNav li, a[data-toggle="tab"], .nav-tabs li, [id^="header_tab_"]', function () {
            setTimeout(initEmployerAutocomplete, 80);
            setTimeout(initEmployerAutocomplete, 250);
        });
    });

})(jQuery);
