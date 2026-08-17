<?php

/**
 * Practice Settings - Employer Save (Add / Edit)
 *
 * @package   OpenEMR Modules
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;

if (!AclMain::aclCheckCore('admin', 'practice')) {
    echo xlt('Unauthorized');
    exit;
}

//$session = SessionWrapperFactory::getInstance()->getActiveSession();

//CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);

$id           = (int)($_POST['id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$phone        = trim($_POST['phone'] ?? '');
$street       = trim($_POST['street'] ?? '');
$street_line_2 = trim($_POST['street_line_2'] ?? '');
$city         = trim($_POST['city'] ?? '');
$state        = trim($_POST['state'] ?? '');
$postal_code  = trim($_POST['postal_code'] ?? '');
$country      = trim($_POST['country'] ?? '');

if (empty($name)) {
    die(xlt('Employer name is required.'));
}

if ($id > 0) {
    sqlStatement(
        "UPDATE `practice_employers` SET `name`=?, `phone`=?, `street`=?, `street_line_2`=?,
         `city`=?, `state`=?, `postal_code`=?, `country`=? WHERE `id`=?",
        [$name, $phone, $street, $street_line_2, $city, $state, $postal_code, $country, $id]
    );
} else {
    sqlStatement(
        "INSERT INTO `practice_employers` (`name`, `phone`, `street`, `street_line_2`, `city`, `state`, `postal_code`, `country`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $phone, $street, $street_line_2, $city, $state, $postal_code, $country]
    );
}

header('Location: employer_list.php');
exit;
