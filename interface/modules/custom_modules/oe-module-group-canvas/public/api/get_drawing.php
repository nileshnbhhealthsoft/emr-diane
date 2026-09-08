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
$formId = trim((string)($_GET['form_id'] ?? ''));
$groupId = trim((string)($_GET['group_id'] ?? ''));
$formInstanceId = (int)($_GET['form_instance_id'] ?? ($_GET['id'] ?? ($_GET['formid'] ?? 0)));
$pid = !empty($_GET['pid']) ? (int)$_GET['pid'] : (int)($session->get('pid') ?? ($_SESSION['pid'] ?? ($GLOBALS['pid'] ?? 0)));
$encounter = !empty($_GET['encounter']) ? (int)$_GET['encounter'] : (int)($session->get('encounter') ?? ($_SESSION['encounter'] ?? ($GLOBALS['encounter'] ?? 0)));

// If form_instance_id is provided and pid or encounter is missing, resolve from OpenEMR forms table
if ($formInstanceId > 0 && ($pid <= 0 || $encounter <= 0) && !empty($formId)) {
    $altFid = str_starts_with($formId, 'LBF_') ? substr($formId, 4) : 'LBF_' . $formId;
    $frow = sqlQuery(
        "SELECT `pid`, `encounter` FROM `forms` WHERE `form_id` = ? AND (`formdir` = ? OR `formdir` = ?) AND `deleted` = 0 LIMIT 1",
        [$formInstanceId, $formId, $altFid]
    );
    if (!empty($frow['pid'])) {
        if ($pid <= 0) {
            $pid = (int)$frow['pid'];
        }
        if ($encounter <= 0) {
            $encounter = (int)$frow['encounter'];
        }
    }
}

if (($pid <= 0 && $formInstanceId <= 0) || empty($formId) || empty($groupId)) {
    echo json_encode(['success' => false, 'message' => xl('Missing required parameters'), 'pid' => $pid, 'encounter' => $encounter, 'form_instance_id' => $formInstanceId]);
    exit;
}

$controller = new GroupCanvasApiController();
$response = $controller->getDrawingData($pid, $encounter, $formId, $groupId, $formInstanceId);

echo json_encode($response);
exit;
