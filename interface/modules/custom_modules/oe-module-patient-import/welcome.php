<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 4) . "/globals.php";

use OpenEMR\Core\Header;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo xlt("Welcome | Patient Demographics Import"); ?></title>
    <?php Header::setupHeader(['common', 'fontawesome']); ?>
    <style>
        body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .welcome-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            padding: 36px;
            margin-top: 30px;
        }
        .header-badge {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 12px;
        }
        .feature-icon {
            background: #eef2ff;
            color: #4f46e5;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="container" style="max-width: 850px;">
    <div class="welcome-card">
        <div class="header-badge">
            <i class="fa fa-file-excel mr-2"></i> <?php echo xlt("Patient Demographics Import Module"); ?>
        </div>
        <h2 class="font-weight-bold mb-3"><?php echo xlt("Import Patient Demographics Effortlessly"); ?></h2>
        <p class="text-muted lead" style="font-size: 1.05rem;">
            <?php echo xlt("This module enables seamless importing and mapping of patient demographics data from CSV and Excel (.xlsx, .xls) files directly into OpenEMR."); ?>
        </p>

        <hr class="my-4">

        <h5 class="font-weight-bold mb-3"><?php echo xlt("Key Features"); ?></h5>
        
        <div class="feature-item">
            <div class="feature-icon"><i class="fa fa-columns"></i></div>
            <div>
                <strong><?php echo xlt("Smart Column Mapping"); ?>:</strong>
                <span class="text-muted"><?php echo xlt("Automatically matches CSV/Excel headers with OpenEMR demographic fields with live preview & override dropdowns."); ?></span>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon"><i class="fa fa-copy"></i></div>
            <div>
                <strong><?php echo xlt("Duplicate Handling"); ?>:</strong>
                <span class="text-muted"><?php echo xlt("Choose to skip existing records, update existing patients by SSN or Name+DOB, or create new patient entries."); ?></span>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon"><i class="fa fa-calendar-alt"></i></div>
            <div>
                <strong><?php echo xlt("Flexible Date Parsing"); ?>:</strong>
                <span class="text-muted"><?php echo xlt("Intelligently converts multiple date formats (MM/DD/YYYY, YYYY-MM-DD, DD/MM/YYYY, Excel date serials) into database standard format."); ?></span>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon"><i class="fa fa-download"></i></div>
            <div>
                <strong><?php echo xlt("Sample Templates"); ?>:</strong>
                <span class="text-muted"><?php echo xlt("Download pre-formatted CSV or Excel templates ready to fill with patient demographic data."); ?></span>
            </div>
        </div>

        <div class="mt-4 pt-2">
            <a href="<?php echo attr(dirname($_SERVER['SCRIPT_NAME']) . '/public/index.php'); ?>" class="btn btn-primary btn-lg px-4" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); border: none;">
                <i class="fa fa-upload mr-2"></i> <?php echo xlt("Launch Patient Import"); ?>
            </a>
        </div>
    </div>
</div>
</body>
</html>
