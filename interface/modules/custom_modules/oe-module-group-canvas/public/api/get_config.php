<?php

/**
 * get_config.php
 *
 * API endpoint to get enabled group canvas configs for a form
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Antigravity AI
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasApiController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$formId = trim((string)($_GET['form_id'] ?? ''));
$pid = (int)($_GET['pid'] ?? ($session->get('pid') ?? 0));
$encounter = (int)($_GET['encounter'] ?? ($session->get('encounter') ?? 0));
$isAdmin = !empty($_GET['is_admin']);

if (empty($formId)) {
    echo json_encode(['success' => false, 'configs' => []]);
    exit;
}

$controller = new GroupCanvasApiController();
$response = $controller->getFormConfigs($formId, $pid, $encounter, $isAdmin);

echo json_encode($response);
exit;
