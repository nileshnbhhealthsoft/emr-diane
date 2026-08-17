<?php

/**
 * Practice Settings - Employer List (Add / Edit / Delete)
 *
 * @package   OpenEMR Modules
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('admin', 'practice')) {
    echo xlt('Unauthorized');
    exit;
}

$employers = sqlStatement("SELECT * FROM `practice_employers` ORDER BY `name` ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo xlt('Employer List'); ?></title>
    <?php Header::setupHeader(['common', 'fontawesome']); ?>
    <style>
        body { background-color: #f5f6f9; }
        .employer-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: visible;
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            border: none;
            padding: 18px 24px;
            border-radius: 8px 8px 0 0 !important;
        }
        .card-header h2 {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-body { padding: 24px; }
        .section-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .search-box {
            position: relative;
            flex: 1 1 260px;
            max-width: 320px;
        }
        .search-box input.form-control {
            padding-left: 38px;
            border-radius: 22px;
            border: 1px solid #d0d4da;
            height: 36px;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #8a9099;
            font-size: 0.9rem;
            pointer-events: none;
        }
        .btn-add {
            border-radius: 6px;
            font-weight: 600;
            padding: 8px 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .table-responsive {
            border-radius: 6px;
            overflow: hidden;
        }
        .table { margin-bottom: 0; }
        .table thead th {
            background-color: #f0f3f7;
            border: none;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
        }
        .table tbody td {
            padding: 11px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #e6e9ef;
        }
        .table tbody tr:hover { background-color: #f0f6ff; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge { font-size: 0.78rem; }
        .action-btn {
            border-radius: 5px;
            width: 34px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin-right: 5px;
            transition: all 0.15s ease;
        }
        .action-btn:hover { transform: translateY(-1px); }
        .btn-edit:hover { box-shadow: 0 2px 6px rgba(255, 193, 7, 0.35); }
        .btn-del:hover { box-shadow: 0 2px 6px rgba(220, 53, 69, 0.35); }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7a7a7a;
        }
        .empty-state i { font-size: 3rem; color: #d0d4da; margin-bottom: 16px; }
        .empty-state h4 { font-weight: 600; margin-bottom: 6px; }
        .modal-content { border-radius: 8px; }
        .modal-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e6e9ef;
            padding: 16px 24px;
        }
        .modal-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-body { padding: 24px; }
        .modal-footer {
            background-color: #f8f9fc;
            border-top: 1px solid #e6e9ef;
            padding: 14px 24px;
            gap: 10px;
        }
        .form-group label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 0.88rem;
        }
        .form-group.required::after {
            content: " *";
            color: #e53e3e;
        }
        .form-control {
            border-radius: 6px;
            border: 1px solid #d0d4da;
            padding: 8px 12px;
            height: 36px;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .btn-modal-save { border-radius: 6px; font-weight: 600; }
        .btn-modal-cancel {
            border-radius: 6px;
            font-weight: 600;
        }
        .divider { height: 1px; background: #e6e9ef; }
        @media (max-width: 768px) {
            .employer-container { padding: 16px; }
            .card-header h2 { font-size: 1.15rem; }
            .action-btn { margin-right: 3px; }
        }
    </style>
    <script>
    function openEmployerModal(id, data) {
        var title = id > 0 ? <?php echo xlj('Edit Employer'); ?> : <?php echo xlj('Add Employer'); ?>;
        var icon = id > 0 ? 'fa-pencil-alt' : 'fa-plus';
        var modalTitle = document.getElementById('employerModalTitle');
        modalTitle.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + title;
        document.getElementById('emp_id').value = id || 0;
        
        if (typeof data === 'string') {
            data = JSON.parse(data);
        }

        var fields = ['name', 'phone', 'street', 'street_line_2', 'city', 'state', 'postal_code', 'country'];
        fields.forEach(function(f) {
            var el = document.getElementById('emp_' + f);
            if (el) {
                el.value = (data && data[f]) ? data[f] : '';
            }
        });

        $('#employerModal').modal('show');
    }

    function deleteEmployer(id) {
        if (!confirm(<?php echo xlj('Are you sure you want to delete this employer?'); ?>)) return;
        top.restoreSession();
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'employer_delete.php';
        form.innerHTML = '<input name="id" value="' + encodeURIComponent(id) + '">' +
            '<input name="csrf_token_form" value="<?php echo CsrfUtils::collectCsrfToken(session: $session); ?>">';
        document.body.appendChild(form);
        form.submit();
    }

    document.getElementById('employerForm').addEventListener('submit', function() {
        top.restoreSession();
    });

    // Search/filter functionality
    document.getElementById('employerSearch').addEventListener('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        $('#employerTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
        });
    });
    /*var employerSearch = document.getElementById('employerSearch');

    if (employerSearch) {
        employerSearch.addEventListener('keyup', function() {
            var searchText = this.value.toLowerCase();

            var table = document.getElementById('employerTable');

            if (!table) {
                return;
            }

            var rows = table.querySelectorAll('tbody tr');

            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();

                row.style.display =
                    text.indexOf(searchText) > -1 ? '' : 'none';
            });
        });
    }*/
    </script>
</head>
<body>
<div class="employer-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="far fa-building"></i> <?php echo xlt('Employer List'); ?></h2>
        </div>
        <div class="card-body">
            <div class="section-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="employerSearch" class="form-control"
                           placeholder="<?php echo xlt('Search employers...'); ?>">
                </div>
                <button class="btn btn-primary" onclick="openEmployerModal(0)">
                    <i class="fas fa-plus"></i> <?php echo xlt('Add Employer'); ?>
                </button>
            </div>

            <?php
            $hasRows = sqlNumRows($employers) > 0;
            if ($hasRows):
            ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="employerTable">
                        <thead>
                            <tr>
                                <th><?php echo xlt('Name'); ?></th>
                                <th><?php echo xlt('City'); ?></th>
                                <th><?php echo xlt('State'); ?></th>
                                <th><?php echo xlt('Postal Code'); ?></th>
                                <th><?php echo xlt('Country'); ?></th>
                                <th><?php echo xlt('Phone'); ?></th>
                                <th class="text-end"><?php echo xlt('Actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = sqlFetchArray($employers)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo text($row['name']); ?></strong>
                                </td>
                                <td><?php echo text($row['city']); ?></td>
                                <td><?php echo text($row['state']); ?></td>
                                <td><?php echo text($row['postal_code']); ?></td>
                                <td><?php echo text($row['country']); ?></td>
                                <td><?php echo text($row['phone']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-warning btn-sm action-btn"
                                        onclick='openEmployerModal(<?php echo attr_js($row["id"]); ?>, <?php echo js_escape(json_encode($row)); ?>)'
                                        title="<?php echo xlt('Edit'); ?>">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm action-btn btn-del"
                                        onclick="deleteEmployer(<?php echo attr_js($row['id']); ?>)"
                                        title="<?php echo xlt('Delete'); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h4><?php echo xlt('No employers found'); ?></h4>
                    <p><?php echo xlt('Click the "Add Employer" button to create your first employer.'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="employerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employerModalTitle">
                    <i class="fas fa-building"></i> <?php echo xlt('Employer'); ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo xlt('Close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="employerForm" method="post" action="employer_save.php">
                <input type="hidden" name="csrf_token_form" value="<?php echo CsrfUtils::collectCsrfToken(session: $session); ?>">
                <input type="hidden" name="id" id="emp_id" value="0">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6 required">
                            <label for="emp_name"><?php echo xlt('Employer Name'); ?></label>
                            <input type="text" class="form-control" name="name" id="emp_name" maxlength="255" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="emp_phone"><?php echo xlt('Phone'); ?></label>
                            <input type="text" class="form-control" name="phone" id="emp_phone" maxlength="50"
                                   placeholder="e.g. (555) 123-4567">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="emp_street"><?php echo xlt('Street'); ?></label>
                            <input type="text" class="form-control" name="street" id="emp_street" maxlength="255"
                                   placeholder="<?php echo xlt('Street address'); ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="emp_street_line_2"><?php echo xlt('Street Line 2'); ?></label>
                            <input type="text" class="form-control" name="street_line_2" id="emp_street_line_2" maxlength="255"
                                   placeholder="<?php echo xlt('Apt, Suite, etc.'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="emp_city"><?php echo xlt('City'); ?></label>
                            <input type="text" class="form-control" name="city" id="emp_city" maxlength="255">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="emp_state"><?php echo xlt('State'); ?></label>
                            <input type="text" class="form-control" name="state" id="emp_state" maxlength="50"
                                   placeholder="e.g. NY">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="emp_postal_code"><?php echo xlt('Postal Code'); ?></label>
                            <input type="text" class="form-control" name="postal_code" id="emp_postal_code" maxlength="20">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="emp_country"><?php echo xlt('Country'); ?></label>
                            <input type="text" class="form-control" name="country" id="emp_country" maxlength="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modal-cancel" data-dismiss="modal">
                        <?php echo xlt('Cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary btn-modal-save">
                        <i class="fas fa-save"></i> <?php echo xlt('Save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


</body>
</html>
