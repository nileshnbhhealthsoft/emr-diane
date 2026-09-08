<?php

/**
 * get_config.php
 *
 * API endpoint to get enabled group canvas configs for a form
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
$formInstanceId = (int)($_GET['form_instance_id'] ?? ($_GET['id'] ?? ($_GET['formid'] ?? 0)));
$pid = !empty($_GET['pid']) ? (int)$_GET['pid'] : (int)($session->get('pid') ?? ($_SESSION['pid'] ?? ($GLOBALS['pid'] ?? 0)));
$encounter = !empty($_GET['encounter']) ? (int)$_GET['encounter'] : (int)($session->get('encounter') ?? ($_SESSION['encounter'] ?? ($GLOBALS['encounter'] ?? 0)));
$isAdmin = !empty($_GET['is_admin']);

if (empty($formId)) {
    echo json_encode(['success' => false, 'configs' => []]);
    exit;
}

$controller = new GroupCanvasApiController();
$response = $controller->getFormConfigs($formId, $pid, $encounter, $isAdmin, $formInstanceId);

echo json_encode($response);
exit;
