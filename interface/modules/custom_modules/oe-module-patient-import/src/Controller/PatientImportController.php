<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\PatientImport\Controller;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\PatientImport\Services\PatientImportService;

class PatientImportController
{
    /**
     * Handles incoming AJAX actions for parsing, mapping, and import execution.
     *
     * @return void
     */
    public function handleAjaxRequest(): void
    {
        header('Content-Type: application/json');

        if (!AclMain::aclCheckCore('admin', 'practice') && !AclMain::aclCheckCore('patients', 'demo', '', 'write')) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized access. You do not have permission to import patient data.'
            ]);
            exit;
        }

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        try {
            if ($action === 'parse') {
                if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No file uploaded or upload error occurred.'
                    ]);
                    exit;
                }

                $tmpName = $_FILES['file']['tmp_name'];
                $fileName = $_FILES['file']['name'];

                $result = PatientImportService::parseUploadedFile($tmpName, $fileName);
                echo json_encode($result);
                exit;
            } elseif ($action === 'import') {
                $tempKey = trim((string)($_POST['temp_key'] ?? ''));
                $mapping = $_POST['mapping'] ?? [];
                $duplicateStrategy = trim((string)($_POST['duplicate_strategy'] ?? 'skip'));

                if (empty($tempKey)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing temporary upload key.'
                    ]);
                    exit;
                }

                if (!is_array($mapping) || empty($mapping)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing column mapping configuration.'
                    ]);
                    exit;
                }

                $session = SessionWrapperFactory::getInstance()->getActiveSession();
                $authUserId = (int)($session->get('authUserID') ?: 1);

                $result = PatientImportService::processImport($tempKey, $mapping, $duplicateStrategy, $authUserId);
                echo json_encode($result);
                exit;
            } elseif ($action === 'history') {
                $logs = [];
                $res = sqlStatement("SELECT * FROM module_patient_import_logs ORDER BY id DESC LIMIT 15");
                if ($res) {
                    while ($row = sqlFetchArray($res)) {
                        $logs[] = [
                            'id' => (int)$row['id'],
                            'filename' => (string)$row['filename'],
                            'file_type' => (string)$row['file_type'],
                            'total_rows' => (int)$row['total_rows'],
                            'imported_count' => (int)$row['imported_count'],
                            'updated_count' => (int)$row['updated_count'],
                            'skipped_count' => (int)$row['skipped_count'],
                            'error_count' => (int)$row['error_count'],
                            'duplicate_mode' => (string)$row['duplicate_mode'],
                            'created_at' => (string)$row['created_at']
                        ];
                    }
                }
                echo json_encode([
                    'success' => true,
                    'history' => $logs
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unknown action requested.'
                ]);
                exit;
            }
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}
