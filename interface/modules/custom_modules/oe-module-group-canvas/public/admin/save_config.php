<?php

/**
 * save_config.php
 *
 * AJAX / POST handler for saving group canvas settings in admin UI
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Antigravity AI
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\GroupCanvas\Controller\GroupCanvasAdminController;

header('Content-Type: application/json');

$session = SessionWrapperFactory::getInstance()->getActiveSession();
if (!CsrfUtils::checkCsrfToken($_POST['csrf_token_form'] ?? '', session: $session)) {
    echo json_encode(['success' => false, 'message' => xl('Invalid CSRF token')]);
    exit;
}

$controller = new GroupCanvasAdminController();
$response = $controller->saveConfiguration($_POST, $_FILES);

echo json_encode($response);
exit;
