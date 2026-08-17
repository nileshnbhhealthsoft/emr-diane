<?php

/*
 * package   OpenEMR
 * link      https://www.open-emr.org
 * author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * Copyright (c) 2024 Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 5) . "/globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;

header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
$session = SessionWrapperFactory::getInstance()->getActiveSession();
$userId = $session->get('authUserID') ?? ($session->get('authUser') ?? ($_SESSION['authUserID'] ?? ($_SESSION['authUser'] ?? null)));

if (empty($userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized - Session required']);
    exit;
}

$term = trim($_GET['term'] ?? '');

$employers = [];
if ($term !== '') {
    $sql = "SELECT id, name, phone, street, street_line_2, city, state, postal_code, country 
            FROM `practice_employers` 
            WHERE `name` LIKE ? OR `city` LIKE ? OR `state` LIKE ?
            ORDER BY `name` ASC LIMIT 25";
    $searchPattern = '%' . $term . '%';
    $res = sqlStatement($sql, [$searchPattern, $searchPattern, $searchPattern]);
} else {
    $sql = "SELECT id, name, phone, street, street_line_2, city, state, postal_code, country 
            FROM `practice_employers` 
            ORDER BY `name` ASC LIMIT 50";
    $res = sqlStatement($sql);
}

if ($res) {
    while ($row = sqlFetchArray($res)) {
        $locationParts = array_filter([$row['city'] ?? '', $row['state'] ?? '']);
        $locationStr = !empty($locationParts) ? ' (' . implode(', ', $locationParts) . ')' : '';

        $employers[] = [
            'id' => (int)$row['id'],
            'value' => (string)$row['name'],
            'label' => (string)$row['name'] . $locationStr,
            'name' => (string)$row['name'],
            'phone' => (string)($row['phone'] ?? ''),
            'street' => (string)($row['street'] ?? ''),
            'street_line_2' => (string)($row['street_line_2'] ?? ''),
            'city' => (string)($row['city'] ?? ''),
            'state' => (string)($row['state'] ?? ''),
            'postal_code' => (string)($row['postal_code'] ?? ''),
            'country' => (string)($row['country'] ?? '')
        ];
    }
}

echo json_encode($employers);
