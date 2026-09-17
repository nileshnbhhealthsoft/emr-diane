<?php

/**
 * get_image.php
 *
 * Secure image streaming endpoint for Group Canvas background diagrams
 * Retrieves uploaded images stored in sites/{site_id}/documents/upload/
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 6) . '/globals.php';

use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;

$fileParam = trim((string)($_GET['file'] ?? ''));
if (empty($fileParam)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Filename parameter missing';
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($fileParam);

// Validate extension against allowed image types
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'webp' => 'image/webp'
];

if (!isset($mimeTypes[$ext])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden file type';
    exit;
}

// Determine site directory
$session = SessionWrapperFactory::getInstance()->getActiveSession();
$siteId = $session->get('site_id') ?? ($_SESSION['site_id'] ?? ($GLOBALS['site_id'] ?? 'default'));
$siteDir = '';
try {
    $kernel = OEGlobalsBag::getInstance()->getKernel();
    if ($kernel) {
        $siteDir = $kernel->getSiteDir($siteId);
    }
} catch (\Throwable $e) {
}
if (!$siteDir && OEGlobalsBag::getInstance()->has('OE_SITE_DIR')) {
    $siteDir = OEGlobalsBag::getInstance()->get('OE_SITE_DIR');
}
if (!$siteDir) {
    $siteDir = OEGlobalsBag::getInstance()->getProjectDir() . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;
}

$defaultSiteDir = OEGlobalsBag::getInstance()->getProjectDir() . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'default';

// Search candidate locations in order of priority
$candidatePaths = [
    $siteDir . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $filename,
    $siteDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename,
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $filename,
    $siteDir . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $filename,
    $defaultSiteDir . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $filename,
    $defaultSiteDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename
];

$targetPath = null;
foreach ($candidatePaths as $path) {
    if (file_exists($path) && is_file($path)) {
        $targetPath = $path;
        break;
    }
}

if ($targetPath === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Image not found';
    exit;
}

// Stream image to client
$mime = $mimeTypes[$ext];
$filesize = filesize($targetPath);

// Clear any existing output buffer to prevent corrupting image stream
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$filesize);
header('Cache-Control: public, max-age=86400');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

readfile($targetPath);
exit;
