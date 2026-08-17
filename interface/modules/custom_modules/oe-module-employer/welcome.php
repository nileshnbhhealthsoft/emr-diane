<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

require_once dirname(__FILE__, 4) . "/globals.php";

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo xlt("Welcome | Employer Manager"); ?></title>
    <?php use OpenEMR\Core\Header; Header::setupHeader() ?>
</head>
<body>
<div class="container">
    <div class="m-5">
        <h1><?php echo xlt("Employer Manager") ?></h1>
    </div>
    <div class="m-5">
        <p><?php echo xlt("Thank you for selecting our Employer module to help your practice/clinic") ?></p>
        <p><?php echo xlt("This module manages employer records for your patients, allowing you to track employment history,
        occupation and industry for each patient.") ?></p>
        <p><strong><?php echo xlt("The module is fully functional and integrates with the existing employer_data table"); ?></strong>.</p>
        <p><?php echo xlt("The module was developed by") ?>
            <a href="https://www.open-emr.org"  target="_blank" >
                <?php echo xlt("OpenEMR Community") ?></a></p>
        <p>&copy; <?php echo date('Y')?> <?php echo xlt("OpenEMR"); ?></p>
    </div>
</div>
</body>
</html>