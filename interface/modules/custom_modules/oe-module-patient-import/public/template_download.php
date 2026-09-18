<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 5) . "/globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\PatientImport\Services\PatientImportService;

if (!AclMain::aclCheckCore('admin', 'practice') && !AclMain::aclCheckCore('patients', 'demo', '', 'write')) {
    echo xlt('Unauthorized');
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

if ($format === 'xlsx') {
    PatientImportService::downloadSampleXlsx();
} else {
    $csvData = PatientImportService::generateSampleCsv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patient_demographics_sample_template.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csvData;
    exit;
}
