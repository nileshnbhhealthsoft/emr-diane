<?php
/**
 * save_hidden_field.php
 *
 * API endpoint to save or update Encounter Summary Hide state for a layout field
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasApiController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
if (!AclMain::aclCheckCore('admin', 'super') && !AclMain::aclCheckCore('admin', 'practice')) {
    echo json_encode(['success' => false, 'message' => xl('Access denied')]);
    exit;
}

$formId = trim((string)($_POST['form_id'] ?? ''));
$fieldId = trim((string)($_POST['field_id'] ?? ''));
$isHiddenRaw = $_POST['is_hidden'] ?? 0;
$isHidden = ($isHiddenRaw === '1' || $isHiddenRaw === 1 || $isHiddenRaw === 'true' || $isHiddenRaw === true);

if (empty($formId) || empty($fieldId)) {
    echo json_encode(['success' => false, 'message' => xl('Form ID and Field ID are required')]);
    exit;
}

$controller = new GroupCanvasApiController();
$response = $controller->saveHiddenField($formId, $fieldId, $isHidden);

echo json_encode($response);
exit;
