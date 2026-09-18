<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 5) . "/globals.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\PatientImport\Controller\PatientImportController;

// Enforce CSRF token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '');
    if (!CsrfUtils::verifyCsrfToken($csrfToken, session: $session)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF verification failed or session expired. Please refresh the page.'
        ]);
        exit;
    }
}

$controller = new PatientImportController();
$controller->handleAjaxRequest();
