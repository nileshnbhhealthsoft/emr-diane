<?php

/**
 * auth_context.js.php
 *
 * Provides authenticated user session context as Javascript for client-side forms.
 *
 * @package OpenEMR
 * @author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Session\SessionWrapperFactory;

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$session = SessionWrapperFactory::getInstance()->getActiveSession();

$authUserId = (int)($session->get('authUserID') ?? ($_SESSION['authUserID'] ?? ($GLOBALS['authUserID'] ?? 0)));
$authUserName = (string)($session->get('authUser') ?? ($_SESSION['authUser'] ?? ($GLOBALS['authUser'] ?? '')));
$authProviderName = (string)($session->get('authProvider') ?? ($_SESSION['authProvider'] ?? ($GLOBALS['authProvider'] ?? '')));

$userRow = $authUserId > 0 ? sqlQuery("SELECT fname, lname FROM users WHERE id = ? LIMIT 1", [$authUserId]) : null;
$userFullName = $userRow ? trim(($userRow['fname'] ?? '') . ' ' . ($userRow['lname'] ?? '')) : '';

$authData = [
    'auth_user_id' => $authUserId,
    'auth_user_name' => $authUserName,
    'auth_user_fullname' => $userFullName,
    'auth_provider' => $authProviderName,
];
?>
window.oeModuleAuthUser = <?php echo json_encode($authData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
