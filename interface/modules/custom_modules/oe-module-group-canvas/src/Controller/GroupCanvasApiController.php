<?php

/**
 * GroupCanvasApiController.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\GroupCanvas\Controller;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\GroupCanvas\Model\GroupCanvasConfigModel;
use OpenEMR\Modules\GroupCanvas\Model\GroupCanvasDataModel;

class GroupCanvasApiController
{
    private GroupCanvasConfigModel $configModel;
    private GroupCanvasDataModel $dataModel;

    public function __construct()
    {
        $this->configModel = new GroupCanvasConfigModel();
        $this->dataModel = new GroupCanvasDataModel();
    }

    /**
     * Get active group canvas configs for a layout form (or multiple forms) and check if any drawings exist for this encounter
     *
     * @param string|array $formId Single form ID or array / comma-delimited form IDs
     * @param int $pid
     * @param int $encounter
     * @param bool $isAdmin
     * @param int $formInstanceId
     * @return array
     */
    public function getFormConfigs($formId, int $pid = 0, int $encounter = 0, bool $isAdmin = false, int $formInstanceId = 0): array
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        if ($pid <= 0 && $session->get('pid')) {
            $pid = (int)$session->get('pid');
        }
        if ($encounter <= 0 && $session->get('encounter')) {
            $encounter = (int)$session->get('encounter');
        }

        $formIds = is_array($formId) ? $formId : array_filter(array_map('trim', explode(',', (string)$formId)));

        // If form_instance_id is provided and pid or encounter is missing, resolve from OpenEMR forms table
        if ($formInstanceId > 0 && ($pid <= 0 || $encounter <= 0) && !empty($formIds)) {
            $firstFid = reset($formIds);
            $altFid = str_starts_with($firstFid, 'LBF_') ? substr($firstFid, 4) : 'LBF_' . $firstFid;
            $frow = sqlQuery(
                "SELECT `pid`, `encounter` FROM `forms` WHERE `form_id` = ? AND (`formdir` = ? OR `formdir` = ?) AND `deleted` = 0 LIMIT 1",
                [$formInstanceId, $firstFid, $altFid]
            );
            if (!empty($frow['pid'])) {
                if ($pid <= 0) {
                    $pid = (int)$frow['pid'];
                }
                if ($encounter <= 0) {
                    $encounter = (int)$frow['encounter'];
                }
            }
        }

        $webroot = OEGlobalsBag::getInstance()->getWebRoot();
        $siteId = $session->get('site_id') ?? 'default';
        $uploadUrl = $webroot . '/interface/modules/custom_modules/oe-module-group-canvas/public/uploads/';
        $siteImagesUrl = $webroot . '/sites/' . $siteId . '/images/';

        $responseConfigs = [];

        foreach ($formIds as $fId) {
            $configs = $this->configModel->getConfigsByForm($fId, !$isAdmin);
            $drawings = [];
            if ($pid > 0 || $formInstanceId > 0) {
                $drawings = $this->dataModel->getDrawingsForEncounter($pid, $encounter, $fId, $formInstanceId);
            }

            foreach ($configs as $cfg) {
                $groupId = $cfg['group_id'];
                $imageFile = $cfg['background_image'];
                $imageUrl = '';
                $imageExists = false;

                if (!empty($imageFile)) {
                    $uploadPath = dirname(__DIR__, 2) . '/public/uploads/' . $imageFile;
                    if (file_exists($uploadPath)) {
                        $imageUrl = $uploadUrl . $imageFile;
                        $imageExists = true;
                    } else {
                        // Fallback to site images
                        $sitePath = OEGlobalsBag::getInstance()->getProjectDir() . '/sites/' . $siteId . '/images/' . $imageFile;
                        if (file_exists($sitePath)) {
                            $imageUrl = $siteImagesUrl . $imageFile;
                            $imageExists = true;
                        }
                    }
                }

                // For clinical form rendering, only include if image actually exists on server
                if (!$isAdmin && !$imageExists) {
                    continue;
                }

                $hasDrawing = isset($drawings[$groupId]);
                $drawingRow = $hasDrawing ? $drawings[$groupId] : null;

                // Fetch group title and sequencing from layout properties
                $grpProp = sqlQuery(
                    "SELECT `grp_title`, `grp_subtitle`, `grp_seq` FROM `layout_group_properties` WHERE `grp_form_id` = ? AND `grp_group_id` = ? LIMIT 1",
                    [$fId, $groupId]
                );
                $groupTitle = !empty($grpProp['grp_title']) ? $grpProp['grp_title'] : '';
                $groupSubtitle = !empty($grpProp['grp_subtitle']) ? $grpProp['grp_subtitle'] : '';
                $groupSeq = isset($grpProp['grp_seq']) ? (int)$grpProp['grp_seq'] : null;

                $responseConfigs[] = [
                    'form_id' => $cfg['form_id'],
                    'group_id' => $groupId,
                    'group_title' => $groupTitle,
                    'group_subtitle' => $groupSubtitle,
                    'group_seq' => $groupSeq,
                    'button_label' => $cfg['button_label'] ?: 'Annotate Diagram',
                    'background_image' => $imageFile,
                    'background_image_url' => $imageUrl,
                    'has_image' => $imageExists,
                    'canvas_width' => (int)($cfg['canvas_width'] ?: 800),
                    'canvas_height' => (int)($cfg['canvas_height'] ?: 600),
                    'has_drawing' => $hasDrawing,
                    'drawing_png' => $drawingRow ? $drawingRow['drawing_png'] : null,
                    'last_updated' => $drawingRow ? $drawingRow['updated_at'] : null
                ];
            }
        }

        return [
            'success' => true,
            'configs' => $responseConfigs,
            'pid' => $pid,
            'encounter' => $encounter,
            'form_instance_id' => $formInstanceId
        ];
    }

    /**
     * Get drawing data for a specific patient, encounter, form, and group
     *
     * @param int $pid
     * @param int $encounter
     * @param string $formId
     * @param string $groupId
     * @param int $formInstanceId
     * @return array
     */
    public function getDrawingData(
        int $pid,
        int $encounter,
        string $formId,
        string $groupId,
        int $formInstanceId = 0
    ): array {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        if ($pid <= 0 && $session->get('pid')) {
            $pid = (int)$session->get('pid');
        }
        if ($encounter <= 0 && $session->get('encounter')) {
            $encounter = (int)$session->get('encounter');
        }

        // Resolve pid and encounter from forms table if missing
        if ($formInstanceId > 0 && ($pid <= 0 || $encounter <= 0) && !empty($formId)) {
            $altFid = str_starts_with($formId, 'LBF_') ? substr($formId, 4) : 'LBF_' . $formId;
            $frow = sqlQuery(
                "SELECT `pid`, `encounter` FROM `forms` WHERE `form_id` = ? AND (`formdir` = ? OR `formdir` = ?) AND `deleted` = 0 LIMIT 1",
                [$formInstanceId, $formId, $altFid]
            );
            if (!empty($frow['pid'])) {
                if ($pid <= 0) {
                    $pid = (int)$frow['pid'];
                }
                if ($encounter <= 0) {
                    $encounter = (int)$frow['encounter'];
                }
            }
        }

        $drawing = $this->dataModel->getDrawing($pid, $encounter, $formId, $groupId, $formInstanceId);
        $config = $this->configModel->getConfigByFormAndGroup($formId, $groupId);

        $webroot = OEGlobalsBag::getInstance()->getWebRoot();
        $siteId = $session->get('site_id') ?? 'default';
        $uploadUrl = $webroot . '/interface/modules/custom_modules/oe-module-group-canvas/public/uploads/';
        $siteImagesUrl = $webroot . '/sites/' . $siteId . '/images/';

        $imageUrl = '';
        if (!empty($config['background_image'])) {
            $uploadPath = dirname(__DIR__, 2) . '/public/uploads/' . $config['background_image'];
            if (file_exists($uploadPath)) {
                $imageUrl = $uploadUrl . $config['background_image'];
            } else {
                $imageUrl = $siteImagesUrl . $config['background_image'];
            }
        }

        return [
            'success' => true,
            'drawing' => $drawing,
            'config' => $config,
            'background_image_url' => $imageUrl,
            'pid' => $pid,
            'encounter' => $encounter,
            'form_instance_id' => $formInstanceId
        ];
    }

    /**
     * Save drawing data
     *
     * @param array $payload
     * @return array
     */
    public function saveDrawingData(array $payload): array
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $pid = (int)($payload['pid'] ?? 0);
        $encounter = (int)($payload['encounter'] ?? 0);

        if ($pid <= 0) {
            $pid = (int)($session->get('pid') ?? ($_SESSION['pid'] ?? ($GLOBALS['pid'] ?? 0)));
        }
        if ($encounter <= 0) {
            $encounter = (int)($session->get('encounter') ?? ($_SESSION['encounter'] ?? ($GLOBALS['encounter'] ?? 0)));
        }

        $formId = trim((string)($payload['form_id'] ?? ''));
        $groupId = trim((string)($payload['group_id'] ?? ''));
        $formInstanceId = (int)($payload['form_instance_id'] ?? 0);
        $drawingData = $payload['drawing_data'] ?? null;
        $drawingPng = $payload['drawing_png'] ?? null;

        if (is_array($drawingData) || is_object($drawingData)) {
            $drawingData = json_encode($drawingData);
        }

        if ($pid <= 0 || empty($formId) || empty($groupId)) {
            return ['success' => false, 'message' => xl('Missing required patient or form identifiers')];
        }

        $userId = (int)($session->get('authUserID') ?? ($session->get('authId') ?? ($_SESSION['authUserID'] ?? ($_SESSION['authId'] ?? ($GLOBALS['authUserID'] ?? 0)))));

        $savedId = $this->dataModel->saveDrawing(
            $pid,
            $encounter,
            $formId,
            $groupId,
            $drawingData,
            $drawingPng,
            $userId,
            $formInstanceId
        );

        if ($savedId > 0) {
            return [
                'success' => true,
                'id' => $savedId,
                'pid' => $pid,
                'encounter' => $encounter,
                'form_id' => $formId,
                'group_id' => $groupId,
                'message' => xl('Drawing saved successfully')
            ];
        }

        return ['success' => false, 'message' => xl('Failed to save drawing')];
    }
}
