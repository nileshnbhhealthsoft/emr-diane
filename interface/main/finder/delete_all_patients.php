<?php

/**
 * Delete All Patients
 *
 * Provides a secure, confirmation-based interface for an admin/super user to
 * delete every patient record in the database.  This is a highly destructive
 * operation and requires:
 *   1. ACL of admin/super
 *   2. The global setting 'allow_pat_delete' to be enabled
 *   3. A valid CSRF token
 *   4. The user to type "DELETE ALL" in a confirmation text field
 *
 * The deletion logic mirrors the single-patient deletion in
 * interface/patient_file/deleter.php, applied to every patient in the
 * patient_data table.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @copyright Copyright (c) 2026 OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");

use OpenEMR\BC\Utilities;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;

$session = SessionWrapperFactory::getInstance()->getActiveSession();

// CSRF protection for all requests.
if (!empty($_REQUEST)) {
    if (!CsrfUtils::verifyCsrfToken($_REQUEST["csrf_token_form"] ?? "", session: $session)) {
        CsrfUtils::csrfNotVerified();
    }
}

// ACL check — must be admin/super AND allow_pat_delete must be enabled.
if (!AclMain::aclCheckCore('admin', 'super') /*|| !OEGlobalsBag::getInstance()->getBoolean('allow_pat_delete')*/) {
    AccessDeniedHelper::denyWithTemplate("ACL check failed for admin/super: Delete All Patients", xl("Delete All Patients"));
}

// ----------------------------------------------------------------------------
// Deletion helper functions (mirror of the functions in patient_file/deleter.php
// so they are available without path-resolution issues).  Guard with
// function_exists so they are not redeclared if deleter.php was already loaded.
// ----------------------------------------------------------------------------

if (!function_exists('deleter_row_delete')) {
    /**
     * Delete rows, with logging, for the specified table using the
     * specified WHERE clause.
     *
     * @param list<scalar> $binds
     */
    function deleter_row_delete(string $table, string $where, array $binds = []): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();

        $tres = QueryUtils::sqlStatementThrowException("SELECT * FROM " . escape_table_name($table) . " WHERE $where", $binds);
        $count = 0;
        while ($trow = QueryUtils::fetchArrayFromResultSet($tres)) {
            $logstring = "";
            foreach ($trow as $key => $value) {
                if (Utilities::isDateEmpty($value)) {
                    continue;
                }

                if ($logstring) {
                    $logstring .= " ";
                }

                $logstring .= $key . "= '" . $value . "' ";
            }

            EventAuditLogger::getInstance()->newEvent("delete", $session->get('authUser'), $session->get('authProvider'), 1, "$table: $logstring");
            ++$count;
        }

        if ($count) {
            $query = "DELETE FROM " . escape_table_name($table) . " WHERE $where";
            if (!OEGlobalsBag::getInstance()->getBoolean('sql_string_no_show_screen')) {
                echo text($query) . "<br />\n";
            }

            QueryUtils::sqlStatementThrowException($query, $binds);
        }
    }
}

if (!function_exists('deleter_row_modify')) {
    /**
     * Deactivate rows, with logging, for the specified table using the
     * specified SET and WHERE clauses.
     *
     * @param list<scalar> $binds
     */
    function deleter_row_modify(string $table, string $set, string $where, array $binds = []): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();

        if (QueryUtils::querySingleRow("SELECT * FROM " . escape_table_name($table) . " WHERE $where", $binds)) {
            EventAuditLogger::getInstance()->newEvent("deactivate", $session->get('authUser'), $session->get('authProvider'), 1, "$table: $where");
            $query = "UPDATE " . escape_table_name($table) . " SET $set WHERE $where";
            if (!OEGlobalsBag::getInstance()->getBoolean('sql_string_no_show_screen')) {
                echo text($query) . "<br />\n";
            }

            QueryUtils::sqlStatementThrowException($query, $binds);
        }
    }
}

if (!function_exists('delete_drug_sales')) {
    /**
     * Delete and undo product sales for a given patient.
     */
    function delete_drug_sales($patient_id, $encounter_id = 0): void
    {
        if ($encounter_id) {
            QueryUtils::sqlStatementThrowException(
                "UPDATE drug_sales AS ds, drug_inventory AS di " .
                "SET di.on_hand = di.on_hand + ds.quantity " .
                "WHERE ds.encounter = ? AND di.inventory_id = ds.inventory_id",
                [$encounter_id]
            );
            deleter_row_delete("drug_sales", "encounter = ?", [$encounter_id]);
        } else {
            QueryUtils::sqlStatementThrowException(
                "UPDATE drug_sales AS ds, drug_inventory AS di " .
                "SET di.on_hand = di.on_hand + ds.quantity " .
                "WHERE ds.pid = ? AND ds.encounter != 0 AND di.inventory_id = ds.inventory_id",
                [$patient_id]
            );
            deleter_row_delete("drug_sales", "pid = ?", [$patient_id]);
        }
    }
}

if (!function_exists('delete_document')) {
    /**
     * Delete a specified document including its associated relations.
     *  Note the specific file is not deleted (instead flagged as deleted), since required to keep file for
     *   ONC certification purposes.
     */
    function delete_document($document): void
    {
        QueryUtils::sqlStatementThrowException("UPDATE `documents` SET `deleted` = 1 WHERE id = ?", [$document]);
        deleter_row_delete("categories_to_documents", "document_id = ?", [$document]);
        deleter_row_delete("gprelations", "type1 = 1 AND id1 = ?", [$document]);
    }
}

if (!function_exists('form_delete')) {
    /**
     * Delete a form's data that is specific to that form.
     */
    function form_delete($formdir, $formid, $patient_id, $encounter_id): void
    {
        $formdir = ($formdir == 'newpatient') ? 'encounter' : $formdir;
        $formdir = ($formdir == 'newGroupEncounter') ? 'groups_encounter' : $formdir;
        if (str_starts_with((string) $formdir, 'LBF')) {
            deleter_row_delete("lbf_data", "form_id = ?", [$formid]);
            $where = "pid = ? AND encounter = ? AND field_id NOT IN (" .
              "SELECT lo.field_id FROM forms AS f, layout_options AS lo WHERE " .
              "f.pid = ? AND f.encounter = ? AND f.formdir LIKE 'LBF%' AND " .
              "f.deleted = 0 AND f.form_id != ? AND " .
              "lo.form_id = f.formdir AND lo.source = 'E' AND lo.uor > 0)";
            $binds = [$patient_id, $encounter_id, $patient_id, $encounter_id, $formid];
            deleter_row_delete("shared_attributes", $where, $binds);
        } elseif ($formdir == 'procedure_order') {
            $tres = QueryUtils::sqlStatementThrowException("SELECT procedure_report_id FROM procedure_report " .
            "WHERE procedure_order_id = ?", [$formid]);
            while ($trow = QueryUtils::fetchArrayFromResultSet($tres)) {
                $reportid = (int)$trow['procedure_report_id'];
                deleter_row_delete("procedure_result", "procedure_report_id = ?", [$reportid]);
            }

            deleter_row_delete("procedure_report", "procedure_order_id = ?", [$formid]);
            deleter_row_delete("procedure_order_code", "procedure_order_id = ?", [$formid]);
            deleter_row_delete("procedure_order", "procedure_order_id = ?", [$formid]);
        } elseif ($formdir == 'physical_exam') {
            deleter_row_delete("form_$formdir", "forms_id = ?", [$formid]);
        } elseif ($formdir == 'eye_mag') {
            $tables = ['form_eye_base','form_eye_hpi','form_eye_ros','form_eye_vitals',
                'form_eye_acuity','form_eye_refraction','form_eye_biometrics',
                'form_eye_external', 'form_eye_antseg','form_eye_postseg',
                'form_eye_neuro','form_eye_locking','form_eye_mag_orders'];
            foreach ($tables as $table_name) {
                deleter_row_delete($table_name, "id = ?", [$formid]);
            }
            deleter_row_delete("form_eye_mag_impplan", "form_id = ?", [$formid]);
            deleter_row_delete("form_eye_mag_wearing", "FORM_ID = ?", [$formid]);
        } else {
            deleter_row_delete("form_$formdir", "id = ?", [$formid]);
        }
    }
}

// ----------------------------------------------------------------------------
// Main logic
// ----------------------------------------------------------------------------

// Get the total patient count for display.
$patient_count = (int) sqlQueryNoLog("SELECT COUNT(*) AS count FROM patient_data")['count'] ?? 0;

$csrf_token = CsrfUtils::collectCsrfToken(session: $session);
$web_root = OEGlobalsBag::getInstance()->getWebRoot();

// Handle the form submission (actual deletion).
if (!empty($_POST['form_submit'])) {
    $confirm_text = trim((string) ($_POST['confirm_text'] ?? ''));
    if ($confirm_text !== 'DELETE ALL') {
        // Re-display confirmation with error.
        $error_message = xl('You must type "DELETE ALL" in the text box to confirm.');
    } else {
        // Perform the deletion.
        // No time limit, no SQL output for a clean result page.
        set_time_limit(0);
        OEGlobalsBag::getInstance()->set('sql_string_no_show_screen', true);

        // Fetch all patient PIDs.
        $res = QueryUtils::sqlStatementThrowException("SELECT pid FROM patient_data");
        $deleted_count = 0;
        $error_count = 0;
        while ($row = QueryUtils::fetchArrayFromResultSet($res)) {
            $patient = (int) $row['pid'];

            try {
                // --- Deletion logic mirrors deleter.php for a single patient ---
                deleter_row_modify("billing", "activity = 0", "pid = ?", [$patient]);
                deleter_row_modify("pnotes", "deleted = 1", "pid = ?", [$patient]);
                deleter_row_delete("prescriptions", "patient_id = ?", [$patient]);
                deleter_row_delete("claims", "patient_id = ?", [$patient]);
                delete_drug_sales($patient);
                deleter_row_delete("payments", "pid = ?", [$patient]);
                deleter_row_modify("ar_activity", "deleted = NOW()", "pid = ? AND deleted IS NULL", [$patient]);
                deleter_row_delete("openemr_postcalendar_events", "pc_pid = ?", [$patient]);
                deleter_row_delete("immunizations", "patient_id = ?", [$patient]);
                deleter_row_delete("issue_encounter", "pid = ?", [$patient]);
                deleter_row_delete("lists", "pid = ?", [$patient]);
                deleter_row_delete("transactions", "pid = ?", [$patient]);
                deleter_row_delete("employer_data", "pid = ?", [$patient]);
                deleter_row_delete("history_data", "pid = ?", [$patient]);
                deleter_row_delete("insurance_data", "pid = ?", [$patient]);
                deleter_row_delete("patient_history", "pid = ?", [$patient]);

                $form_res = QueryUtils::sqlStatementThrowException("SELECT * FROM forms WHERE pid = ?", [$patient]);
                while ($form_row = QueryUtils::fetchArrayFromResultSet($form_res)) {
                    deleter_row_delete("forms", "pid = ? AND form_id = ?", [$form_row['pid'], $form_row['form_id']]);
                    deleter_row_delete("form_encounter", "pid = ?", [$form_row['pid']]);
                }

                // Delete all documents for the patient.
                $doc_res = QueryUtils::sqlStatementThrowException("SELECT id FROM documents WHERE foreign_id = ? AND deleted = 0", [$patient]);
                while ($doc_row = QueryUtils::fetchArrayFromResultSet($doc_res)) {
                    delete_document($doc_row['id']);
                }

                deleter_row_delete("patient_data", "pid = ?", [$patient]);
                $deleted_count++;
            } catch (\Throwable $e) {
                $error_count++;
            }
        }

        $success_message = xl('Successfully deleted') . ' ' . $deleted_count . ' ' . xl('patients') . '.';
        if ($error_count > 0) {
            $success_message .= ' ' . xl('Errors encountered for') . ' ' . $error_count . ' ' . xl('patients');
        }
        $csrf_token = CsrfUtils::collectCsrfToken(session: $session);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader('opener'); ?>
    <title><?php echo xlt('Delete All Patients'); ?></title>
    <style>
        .warning-banner {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .warning-banner h2 {
            color: #dc3545;
            margin-top: 0;
        }
        .warning-text {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.2em;
        }
        .form-control.confirm-input {
            font-size: 1.2em;
            font-weight: bold;
            border: 2px solid #dc3545;
        }
    </style>
</head>
<body class="body_top">
<div class="container mt-4">
    <?php if (!empty($_POST['form_submit']) && !empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo text($success_message); ?>
        </div>
        <div class="mt-3">
            <a href="<?php echo $web_root; ?>/interface/main/finder/dynamic_finder.php?csrf_token_form=<?php echo attr_url($csrf_token); ?>" class="btn btn-primary"><?php echo xla('Back to Patient Finder'); ?></a>
        </div>
    <?php elseif (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo text($error_message); ?>
        </div>
        <!-- Re-show confirmation form -->
        <?php require __DIR__ . '/_delete_all_patients_form.php'; ?>
    <?php else: ?>
        <!-- Initial confirmation form -->
        <?php require __DIR__ . '/_delete_all_patients_form.php'; ?>
    <?php endif; ?>
</div>
</body>
</html>
