<?php

/**
 * get_drawing.php
 *
 * API endpoint to get drawing data for a specific patient, encounter, form, and group
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasApiController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$pid = (int)($_GET['pid'] ?? ($session->get('pid') ?? 0));
$encounter = (int)($_GET['encounter'] ?? ($session->get('encounter') ?? 0));
$formId = trim((string)($_GET['form_id'] ?? ''));
$groupId = trim((string)($_GET['group_id'] ?? ''));
$formInstanceId = (int)($_GET['form_instance_id'] ?? 0);

if ($pid <= 0 || empty($formId) || empty($groupId)) {
    echo json_encode(['success' => false, 'message' => xl('Missing required parameters')]);
    exit;
}

$controller = new GroupCanvasApiController();
$response = $controller->getDrawingData($pid, $encounter, $formId, $groupId, $formInstanceId);

echo json_encode($response);
exit;
