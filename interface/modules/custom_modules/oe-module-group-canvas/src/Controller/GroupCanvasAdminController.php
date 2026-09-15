<?php

/**
 * GroupCanvasAdminController.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\GroupCanvas\Controller;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\GroupCanvas\Model\GroupCanvasConfigModel;

class GroupCanvasAdminController
{
    private GroupCanvasConfigModel $configModel;
    private string $uploadDir;

    public function __construct()
    {
        $this->configModel = new GroupCanvasConfigModel();
        $this->uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * Get site images directory and public web URL info
     *
     * @return array
     */
    private function getSiteImagesInfo(): array
    {
        $session = \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        $siteId = $session->get('site_id') ?? 'default';
        $siteDir = '';
        try {
            $kernel = OEGlobalsBag::getInstance()->getKernel();
            if ($kernel) {
                $siteDir = $kernel->getSiteDir($siteId);
            }
        } catch (\Throwable $e) {
        }
        if (!$siteDir) {
            $siteDir = OEGlobalsBag::getInstance()->getProjectDir() . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;
        }

        $siteImagesDir = $siteDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
        $webroot = OEGlobalsBag::getInstance()->getWebRoot();
        $siteImagesUrl = $webroot . '/sites/' . $siteId . '/images/';

        return [
            'dir' => $siteImagesDir,
            'url' => $siteImagesUrl
        ];
    }

    /**
     * Check if current user is authorized to manage group canvas settings
     *
     * @return bool
     */
    public function checkAuth(): bool
    {
        return (bool)AclMain::aclCheckCore('admin', 'super') || (bool)AclMain::aclCheckCore('admin', 'practice');
    }

    /**
     * Get all forms and their groups for the admin dashboard
     *
     * @return array
     */
    public function getLayoutForms(): array
    {
        return $this->configModel->getAllFormsWithGroups();
    }

    /**
     * Get config for a specific form and group
     *
     * @param string $formId
     * @param string $groupId
     * @return array
     */
    public function getConfig(string $formId, string $groupId): array
    {
        $config = $this->configModel->getConfigByFormAndGroup($formId, $groupId);
        $webroot = OEGlobalsBag::getInstance()->getWebRoot();
        $uploadUrl = $webroot . '/interface/modules/custom_modules/oe-module-group-canvas/public/uploads/';
        $siteInfo = $this->getSiteImagesInfo();

        $imageUrl = '';
        if (!empty($config['background_image'])) {
            $uploadPath = $this->uploadDir . $config['background_image'];
            $sitePath = $siteInfo['dir'] . $config['background_image'];
            if (file_exists($uploadPath)) {
                $imageUrl = $uploadUrl . $config['background_image'];
            } elseif (file_exists($sitePath)) {
                $imageUrl = $siteInfo['url'] . $config['background_image'];
            } else {
                $imageUrl = $uploadUrl . $config['background_image'];
            }
        }

        return [
            'success' => true,
            'config' => $config,
            'image_url' => $imageUrl
        ];
    }

    /**
     * Handle saving group canvas configuration and file upload
     *
     * @param array $post
     * @param array $files
     * @return array Response array [success => bool, message => string]
     */
    public function saveConfiguration(array $post, array $files): array
    {
        if (!$this->checkAuth()) {
            return ['success' => false, 'message' => xl('Access denied')];
        }

        $formId = trim((string)($post['form_id'] ?? ''));
        $groupId = trim((string)($post['group_id'] ?? ''));
        $isEnabled = !isset($post['is_enabled']) || !empty($post['is_enabled']) ? true : false;
        $buttonLabel = trim((string)($post['button_label'] ?? 'Annotate Diagram')) ?: 'Annotate Diagram';
        $canvasWidth = (int)($post['canvas_width'] ?? 800) ?: 800;
        $canvasHeight = (int)($post['canvas_height'] ?? 600) ?: 600;
        $backgroundImage = trim((string)($post['existing_image'] ?? ''));

        if (empty($formId) || empty($groupId)) {
            return ['success' => false, 'message' => xl('Form ID and Group ID are required')];
        }

        // Handle image upload if provided
        if (!empty($files['background_image']['name']) && $files['background_image']['error'] === UPLOAD_ERR_OK) {
            $file = $files['background_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

            if (!in_array($ext, $allowedExts)) {
                return ['success' => false, 'message' => xl('Invalid image format. Allowed: PNG, JPG, JPEG, GIF, SVG, WEBP')];
            }

            // Create a safe, unique filename
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $formId . '_' . $groupId) . '_' . time() . '.' . $ext;
            $siteInfo = $this->getSiteImagesInfo();
            $uploaded = false;

            // 1. Try module upload directory first if writable
            if (!is_dir($this->uploadDir)) {
                @mkdir($this->uploadDir, 0777, true);
            }
            if (is_dir($this->uploadDir) && is_writable($this->uploadDir)) {
                $targetPath = $this->uploadDir . $safeName;
                if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $uploaded = true;
                }
            }

            // 2. If module upload dir is not writable (e.g. Linux permission limits), use OpenEMR site images directory
            if (!$uploaded) {
                if (!is_dir($siteInfo['dir'])) {
                    @mkdir($siteInfo['dir'], 0775, true);
                }
                $targetPath = $siteInfo['dir'] . $safeName;
                if (@move_uploaded_file($file['tmp_name'], $targetPath) || @copy($file['tmp_name'], $targetPath)) {
                    $uploaded = true;
                }
            }

            if ($uploaded) {
                // Remove old uploaded file if different
                if (!empty($backgroundImage) && $backgroundImage !== $safeName) {
                    if (file_exists($this->uploadDir . $backgroundImage)) {
                        @unlink($this->uploadDir . $backgroundImage);
                    }
                    if (file_exists($siteInfo['dir'] . $backgroundImage)) {
                        @unlink($siteInfo['dir'] . $backgroundImage);
                    }
                }
                $backgroundImage = $safeName;
            } else {
                return ['success' => false, 'message' => xl('Failed to save uploaded image. Please check directory write permissions.')];
            }
        }

        // Save to database
        $saved = $this->configModel->saveConfig(
            $formId,
            $groupId,
            $isEnabled,
            $buttonLabel,
            $backgroundImage,
            $canvasWidth,
            $canvasHeight
        );

        if ($saved) {
            $webroot = OEGlobalsBag::getInstance()->getWebRoot();
            $uploadUrl = $webroot . '/interface/modules/custom_modules/oe-module-group-canvas/public/uploads/';
            $siteInfo = $this->getSiteImagesInfo();

            $imageUrl = '';
            if (!empty($backgroundImage)) {
                if (file_exists($this->uploadDir . $backgroundImage)) {
                    $imageUrl = $uploadUrl . $backgroundImage;
                } elseif (file_exists($siteInfo['dir'] . $backgroundImage)) {
                    $imageUrl = $siteInfo['url'] . $backgroundImage;
                } else {
                    $imageUrl = $uploadUrl . $backgroundImage;
                }
            }

            return [
                'success' => true,
                'message' => xl('Configuration saved successfully'),
                'image' => $backgroundImage,
                'image_url' => $imageUrl,
                'has_image' => !empty($backgroundImage),
                'canvas_width' => $canvasWidth,
                'canvas_height' => $canvasHeight,
                'button_label' => $buttonLabel
            ];
        }

        return ['success' => false, 'message' => xl('Database save failed')];
    }

    /**
     * Delete canvas configuration or remove image for a group
     *
     * @param string $formId
     * @param string $groupId
     * @return array
     */
    public function deleteConfiguration(string $formId, string $groupId): array
    {
        if (!$this->checkAuth()) {
            return ['success' => false, 'message' => xl('Access denied')];
        }

        $formId = trim($formId);
        $groupId = trim($groupId);

        if (empty($formId) || empty($groupId)) {
            return ['success' => false, 'message' => xl('Form ID and Group ID are required')];
        }

        $config = $this->configModel->getConfigByFormAndGroup($formId, $groupId);
        if ($config && !empty($config['background_image'])) {
            $siteInfo = $this->getSiteImagesInfo();
            $filePath = $this->uploadDir . $config['background_image'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $siteFilePath = $siteInfo['dir'] . $config['background_image'];
            if (file_exists($siteFilePath)) {
                @unlink($siteFilePath);
            }
        }

        $deleted = $this->configModel->deleteConfig($formId, $groupId);
        if ($deleted) {
            return [
                'success' => true,
                'message' => xl('Canvas image and configuration removed successfully')
            ];
        }

        return ['success' => false, 'message' => xl('Failed to remove configuration')];
    }
}
