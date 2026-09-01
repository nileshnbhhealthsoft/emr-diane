<?php

/**
 * GroupCanvasConfigModel.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\GroupCanvas\Model;

class GroupCanvasConfigModel
{
    /**
     * Get all active and configured group canvas configurations
     *
     * @return array
     */
    public function getAllConfigs(): array
    {
        $sql = "SELECT * FROM `module_group_canvas_config` ORDER BY `form_id`, `group_id`";
        $res = sqlStatement($sql);
        $configs = [];
        while ($row = sqlFetchArray($res)) {
            $configs[] = $row;
        }
        return $configs;
    }

    /**
     * Get config for a specific form_id and group_id
     *
     * @param string $formId
     * @param string $groupId
     * @return array|null
     */
    public function getConfigByFormAndGroup(string $formId, string $groupId): ?array
    {
        $sql = "SELECT * FROM `module_group_canvas_config` WHERE `form_id` = ? AND `group_id` = ? LIMIT 1";
        $row = sqlQuery($sql, [$formId, $groupId]);
        return !empty($row) ? $row : null;
    }

    /**
     * Get all configs for a specific form_id
     *
     * @param string $formId
     * @param bool $onlyWithImage
     * @return array
     */
    public function getConfigsByForm(string $formId, bool $onlyWithImage = false): array
    {
        if ($onlyWithImage) {
            $sql = "SELECT * FROM `module_group_canvas_config` 
                    WHERE `form_id` = ? AND `is_enabled` = 1 AND `background_image` != '' AND `background_image` IS NOT NULL 
                    ORDER BY `group_id`";
        } else {
            $sql = "SELECT * FROM `module_group_canvas_config` WHERE `form_id` = ? ORDER BY `group_id`";
        }
        $res = sqlStatement($sql, [$formId]);
        $configs = [];
        while ($row = sqlFetchArray($res)) {
            $configs[] = $row;
        }
        return $configs;
    }

    /**
     * Delete config for a specific form_id and group_id
     *
     * @param string $formId
     * @param string $groupId
     * @return bool
     */
    public function deleteConfig(string $formId, string $groupId): bool
    {
        $sql = "DELETE FROM `module_group_canvas_config` WHERE `form_id` = ? AND `group_id` = ?";
        return (bool)sqlStatement($sql, [$formId, $groupId]);
    }

    /**
     * Clear image for a specific form_id and group_id
     *
     * @param string $formId
     * @param string $groupId
     * @return bool
     */
    public function deleteImage(string $formId, string $groupId): bool
    {
        $sql = "UPDATE `module_group_canvas_config` SET `background_image` = '' WHERE `form_id` = ? AND `group_id` = ?";
        return (bool)sqlStatement($sql, [$formId, $groupId]);
    }

    /**
     * Save or update a group canvas configuration
     *
     * @param string $formId
     * @param string $groupId
     * @param bool $isEnabled
     * @param string $buttonLabel
     * @param string $backgroundImage
     * @param int $width
     * @param int $height
     * @return bool
     */
    public function saveConfig(
        string $formId,
        string $groupId,
        bool $isEnabled,
        string $buttonLabel = 'Annotate Diagram',
        string $backgroundImage = '',
        int $width = 800,
        int $height = 600
    ): bool {
        $sql = "INSERT INTO `module_group_canvas_config` 
                (`form_id`, `group_id`, `is_enabled`, `button_label`, `background_image`, `canvas_width`, `canvas_height`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                `is_enabled` = VALUES(`is_enabled`),
                `button_label` = VALUES(`button_label`),
                `background_image` = VALUES(`background_image`),
                `canvas_width` = VALUES(`canvas_width`),
                `canvas_height` = VALUES(`canvas_height`)";

        return (bool)sqlStatement($sql, [
            $formId,
            $groupId,
            $isEnabled ? 1 : 0,
            $buttonLabel,
            $backgroundImage,
            $width,
            $height
        ]);
    }

    /**
     * Fetch all layout forms and their groups from OpenEMR layout_group_properties
     *
     * @return array
     */
    public function getAllFormsWithGroups(): array
    {
        // Fetch top-level layouts (where grp_group_id = '')
        $layoutsSql = "SELECT grp_form_id, grp_title, grp_mapping 
                       FROM layout_group_properties 
                       WHERE grp_group_id = '' 
                       ORDER BY grp_mapping, grp_seq, grp_title";
        $layoutRes = sqlStatement($layoutsSql);
        $forms = [];

        while ($lRow = sqlFetchArray($layoutRes)) {
            $formId = $lRow['grp_form_id'];
            $formTitle = $lRow['grp_title'] ?: $lRow['grp_mapping'] ?: $formId;

            // Fetch all non-empty groups for this form
            $groupsSql = "SELECT grp_group_id, grp_title, grp_subtitle 
                          FROM layout_group_properties 
                          WHERE grp_form_id = ? AND grp_group_id != '' 
                          ORDER BY grp_group_id";
            $grpRes = sqlStatement($groupsSql, [$formId]);
            $groups = [];

            while ($gRow = sqlFetchArray($grpRes)) {
                $groupId = $gRow['grp_group_id'];
                $existingConfig = $this->getConfigByFormAndGroup($formId, $groupId);

                $groups[] = [
                    'group_id' => $groupId,
                    'group_title' => $gRow['grp_title'],
                    'group_subtitle' => $gRow['grp_subtitle'],
                    'config' => $existingConfig
                ];
            }

            if (!empty($groups)) {
                $forms[$formId] = [
                    'form_id' => $formId,
                    'form_title' => $formTitle,
                    'groups' => $groups
                ];
            }
        }

        return $forms;
    }
}
