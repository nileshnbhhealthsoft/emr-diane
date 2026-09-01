<?php

/**
 * save_drawing.php
 *
 * API endpoint to save patient drawing annotation data
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasApiController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$userId = (int)($session->get('authUserID') ?? ($session->get('authId') ?? ($_SESSION['authUserID'] ?? ($_SESSION['authId'] ?? ($GLOBALS['authUserID'] ?? 0)))));

// If no user in session, verify if user session exists in globals
if ($userId <= 0 && empty($session->get('authUser')) && empty($_SESSION['authUser'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => xl('Unauthorized session. Please log in.')]);
    exit;
}

// Get JSON or POST body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

if (!is_array($data)) {
    $data = [];
}

// Check CSRF token if present
if (!empty($data['csrf_token_form'])) {
    CsrfUtils::checkCsrfToken($data['csrf_token_form'], session: $session);
}

$controller = new GroupCanvasApiController();
$response = $controller->saveDrawingData($data);

echo json_encode($response);
exit;
