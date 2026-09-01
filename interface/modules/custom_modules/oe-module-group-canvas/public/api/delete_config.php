<?php
/**
 * delete_config.php
 *
 * AJAX handler for deleting group canvas image and configuration from Layout Editor
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasAdminController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
/*if (!empty($_POST['csrf_token_form']) && !CsrfUtils::checkCsrfToken($_POST['csrf_token_form'], session: $session)) {
    echo json_encode(['success' => false, 'message' => xl('Invalid CSRF token')]);
    exit;
}*/

$formId = trim((string)($_POST['form_id'] ?? ''));
$groupId = trim((string)($_POST['group_id'] ?? ''));

$controller = new GroupCanvasAdminController();
$response = $controller->deleteConfiguration($formId, $groupId);

echo json_encode($response);
exit;
