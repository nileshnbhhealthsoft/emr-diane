<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

require_once dirname(__FILE__, 5) . "/globals.php";

use Juggernaut\OpenEMR\Modules\EmployerModule\Controller\EmployerService;
use Juggernaut\OpenEMR\Modules\EmployerModule\Controller\ListEmployers;
use OpenEMR\BC\Utilities;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;

$session = SessionWrapperFactory::getInstance()->getActiveSession();

$pid = $session->get('pid');
function isValid($date, $format = 'Y-m-d'): bool
{
    $dt = DateTime::createFromFormat($format, $date);
    return $dt && $dt->format($format) === $date;
}

if (!empty($_POST['token'])) {
    CsrfUtils::checkCsrfInput(INPUT_POST, key: 'token', dieOnFail: true);

    $postStartDate = DateToYYYYMMDD($_POST['start_date']);
    $startDate = isValid($postStartDate) === true ? $postStartDate : $_POST['start_date'];

    $postEndDate = DateToYYYYMMDD($_POST['end_date']);
    $endDate = isValid($postEndDate) === true ? $postEndDate : $_POST['end_date'];

    $postData = new EmployerService();
    $rawId = $_POST['id'] ?? null;
    $rawId = (ctype_digit((string)$rawId)) ? (int)$rawId : null;
    $postData->setId($rawId);
    $postData->setPid($pid);
    $postData->setName($_POST['name']);
    $postData->setStreet($_POST['street']);
    $postData->setStreetLine2($_POST['street_line_2']);
    $postData->setPostalCode($_POST['postal_code']);
    $postData->setCity($_POST['city']);
    $postData->setState($_POST['state']);
    $postData->setCountry($_POST['country']);
    $postData->setStartDate($startDate);
    $postData->setEndDate($endDate);
    $postData->setOccupation($_POST['occupation']);
    $postData->setIndustry($_POST['industry']);
    $postData->storeEmployerInfo();
}

$listData = new ListEmployers();
$listData->setPid($pid);
$employerList = $listData->getAllEmployers();

const TABLE_TD = "</td><td>";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo xlt('Add Employer'); ?></title>
    <?php Header::setupHeader(['common', 'datetime-picker']) ?>

    <script>
        $(function () {
            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require(OEGlobalsBag::getInstance()->getSrcDir() . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        })

        function refreshme() {
            top.restoreSession();
            location.reload();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="m-4">
            <span style="font-size: xx-large; padding-right: 20px"><?php echo xlt('Employer Manager'); ?></span>
            <a href="../../../../patient_file/summary/demographics.php" onclick="top.restoreSession()"
                title="<?php echo xla('Go Back') ?>">
                <i id="advanced-tooltip" class="fa fa-undo fa-2x" aria-hidden="true"></i></a>

        </div>
        <div class="m-4">
            <?php if (empty($pid)) {
                echo xlt("You must be in a patients Chart to enter this information");
                die;
            } ?>
            <div class="m-3">
                <h3><?php echo xlt('Enter new employer'); ?></h3>
            </div>
            <form id="theform" method="post" action="index.php" onsubmit="top.restoreSession()">
                <input type="hidden" name="token" value="<?php echo attr(CsrfUtils::collectCsrfToken(session: $session)); ?>">
                <input type="hidden" id="id" name="id" value="">
                <div class="form-row">
                    <div class="col">
                        <input class="form-control" id="name" name="name" value="" placeholder="<?php echo xla('Employer Name') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="street" name="street" value="" placeholder="<?php echo xla('Street') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="street_line_2" name="street_line_2" value="" placeholder="<?php echo xla('Street Line 2') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col">
                        <input class="form-control" id="city" name="city" value="" placeholder="<?php echo xla('City') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="state" name="state" value="" placeholder="<?php echo xla('State') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="postal_code" name="postal_code" value="" placeholder="<?php echo xla('Postal Code') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="country" name="country" value="" placeholder="<?php echo xla('Country') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col">
                        <input class="form-control datepicker" id="start_date" name="start_date" value="" placeholder="<?php echo xla('Start Date') ?>" readonly>
                    </div>
                    <div class="col">
                        <input class="form-control datepicker" id="end_date" name="end_date" value="" placeholder="<?php echo xla('End Date') ?>" readonly>
                    </div>
                    <div class="col">
                        <input class="form-control" id="occupation" name="occupation" value="" placeholder="<?php echo xla('Occupation') ?>">
                    </div>
                    <div class="col">
                        <input class="form-control" id="industry" name="industry" value="" placeholder="<?php echo xla('Industry') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col">
                        <input class="form-control btn btn-primary" type="submit" value="<?php echo xla('Save') ?>">
                    </div>
                </div>
            </form>
        </div>
        <div class="m-4">
            <table class="table table-striped">
                <caption><?php echo xla('List of employers'); ?></caption>
                <tr>
                    <th scope="col"><?php echo xlt('Employer Name'); ?></th>
                    <th scope="col"><?php echo xlt('City'); ?></th>
                    <th scope="col"><?php echo xlt('State'); ?></th>
                    <th scope="col"><?php echo xlt('Start Date'); ?></th>
                    <th scope="col"><?php echo xlt('End Date'); ?></th>
                    <th scope="col"><?php echo xlt('Occupation'); ?></th>
                    <th scope="col"><?php echo xlt('Industry'); ?></th>
                    <th scope="col"></th>
                    <th scope="col"></th>
                </tr>
                <?php
                if (!empty($employerList)) {
                    while ($iter = sqlFetchArray($employerList)) {
                        $editData = json_encode($iter) ?: '';
                        print "<tr><td>";
                        print text($iter['name']);
                        print TABLE_TD . text($iter['city']);
                        print TABLE_TD . text($iter['state']);
                        print TABLE_TD . text($iter['start_date']);
                        if (Utilities::isDateEmpty($iter['end_date'])) {
                            print TABLE_TD;
                        } else {
                            print TABLE_TD . text($iter['end_date']);
                        }
                        print TABLE_TD . text($iter['occupation']);
                        print TABLE_TD . text($iter['industry']);
                        print TABLE_TD . " <button class='btn btn-primary' onclick=getRowData(" . attr_js($iter['id']) . ")>" . xlt('Edit') . "</button>
                        <input type='hidden' id='" . attr_js($iter['id']) . "' value='" . attr($editData) . "' ></td>";
                        print "<td><a class='btn btn-danger' href='#' onclick=removeEntry(" . attr_js($iter['id']) . ")>" . xlt('Delete') . "</a></td>";

                        print "</tr>";
                    }
                }
                ?>
            </table>
        </div>
        &copy; <?php echo date('Y') . " Juggernaut Systems Express" ?>
    </div>

    <script>
        function getRowData(jsonData) {
            let dataArray = document.getElementById(jsonData).value;
            const obj = JSON.parse(dataArray);

            document.getElementById('id').value = obj.id;
            document.getElementById('name').value = obj.name;
            document.getElementById('street').value = obj.street;
            document.getElementById('street_line_2').value = obj.street_line_2;
            document.getElementById('postal_code').value = obj.postal_code;
            document.getElementById('city').value = obj.city;
            document.getElementById('state').value = obj.state;
            document.getElementById('country').value = obj.country;
            document.getElementById('start_date').value = obj.start_date;
            document.getElementById('end_date').value = obj.end_date;
            document.getElementById('occupation').value = obj.occupation;
            document.getElementById('industry').value = obj.industry;
        }

        function removeEntry(id) {
            let url = 'deleter.php?id=' + encodeURIComponent(id) + '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken(session: $session)); ?>;
            ;
            dlgopen(url, '_blank', 290, 290, '', 'Delete Entry', {
                buttons: [
                    {text: <?php echo xlj('Done') ?>, style: 'danger btn-sm', close: true}
                ],
                onClosed: 'refreshme'
            })
        }
    </script>

</body>
</html>