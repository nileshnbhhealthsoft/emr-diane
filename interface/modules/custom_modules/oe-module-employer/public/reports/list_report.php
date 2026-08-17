<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

require_once dirname(__FILE__, 6) . "/globals.php";

use Juggernaut\OpenEMR\Modules\EmployerModule\Controller\EmployerService;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;

$data = new EmployerService();
$patients = $data->listPatientEmployers();

?>
<!doctype html>
<html lang="en">
<head>
    <?php Header::setupHeader(['common', 'opener']) ?>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo xlt("List Existing Employers Report"); ?></title>
    <script>
        // opens the demographic and encounter screens in a new window
        function openNewTopWindow(newpid) {
            top.restoreSession();
            top.RTop.location = "<?php echo OEGlobalsBag::getInstance()->getWebRoot(); ?>/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid);
        }
    </script>
</head>
<body>
    <div class="container-lg" style="padding-top: 6em">
        <h1><?php echo xlt("Employers") ?></h1>
        <div class="table">
            <table class="table table-striped">
                <caption><?php echo xlt("Patients with employers"); ?></caption>
                <th scope="col"><?php echo xlt("MRN"); ?></th>
                <th scope="col"><?php echo xlt("Name"); ?></th>
                <th scope="col"><?php echo xlt("Ins"); ?></th>
                <th scope="col"><?php echo xlt("Employer"); ?></th>
                <th scope="col"><?php echo xlt("City"); ?></th>
                <th scope="col"><?php echo xlt("State"); ?></th>
                <th scope="col"><?php echo xlt("Start"); ?></th>
                <th scope="col"><?php echo xlt("End"); ?></th>
                <th scope="col"><?php echo xlt("Occupation"); ?></th>
                <th scope="col"><?php echo xlt("Industry"); ?></th>

                <?php
                $count = 0;
                $name = '';
                while ($iter = sqlFetchArray($patients)) {
                    $pid = !empty($iter['pid']) ? $iter['pid'] : $iter['mrn'];

                    $insurance = EmployerService::insuranceName($pid);

                    if ($name !== $iter['fname'] . " " . $iter['lname']) {
                        print "<tr><td><a href='#' onclick='openNewTopWindow(" . attr_js($pid) . ")'>" . text($pid) . "</a></td>";
                        print "<td><strong>" . text($iter['lname']) . ", " . text($iter['fname']) . "</strong></td>";
                        print "<td style='max-width:75px;'>" . text($insurance) . "</td>";
                    } else {
                        print "<td></td>";
                        print "<td></td>";
                        print "<td></td>";
                    }
                    print "<td>" . text($iter['name']) . "</td>";
                    print "<td>" . text($iter['city']) . "</td>";
                    print "<td>" . text($iter['state']) . "</td>";
                    print "<td>" . text($iter['start_date']) . "</td>";
                    print "<td>" . text($iter['end_date']) . "</td>";
                    print "<td>" . text($iter['occupation']) . "</td>";
                    print "<td>" . text($iter['industry']) . "</td>";

                    print "</tr>";
                    $name = $iter['fname'] . " " . $iter['lname'];
                    $count++;
                }
                ?>
            </table>
            <table>
                <tr>Count <?php echo $count; ?></tr>
            </table>

        </div>
        &copy; <?php echo date('Y') . " Juggernaut Systems Express" ?>
    </div>

</body>
</html>