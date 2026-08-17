<?php

/**
 * Practice Settings - Employer Delete
 *
 * @package   OpenEMR Modules
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;

if (!AclMain::aclCheckCore('admin', 'practice')) {
    echo xlt('Unauthorized');
    exit;
}

//CsrfUtils::csrfNotBreached();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    sqlStatement("DELETE FROM `practice_employers` WHERE `id` = ?", [$id]);
}

header('Location: employer_list.php');
exit;
