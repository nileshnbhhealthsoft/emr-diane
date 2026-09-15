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

    /**
     * Ensure the module_group_canvas_hidden_fields table exists
     */
    public function ensureHiddenFieldsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `module_group_canvas_hidden_fields` (
            `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `form_id` VARCHAR(31) NOT NULL,
            `field_id` VARCHAR(31) NOT NULL,
            `is_hidden` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `form_field_unique` (`form_id`, `field_id`)
        ) ENGINE=InnoDB COMMENT='Fields hidden from Encounter Summary'";

        try {
            sqlStatement($sql);
        } catch (\Throwable $e) {
            // Table may already exist or DB error
        }
    }

    /**
     * Get all hidden field IDs for a specific form_id
     *
     * @param string $formId
     * @return array List of field_ids that are hidden
     */
    public function getHiddenFieldsByForm(string $formId): array
    {
        $this->ensureHiddenFieldsTable();
        $sql = "SELECT `field_id` FROM `module_group_canvas_hidden_fields` WHERE `form_id` = ? AND `is_hidden` = 1";
        $res = sqlStatement($sql, [$formId]);
        $fields = [];
        while ($row = sqlFetchArray($res)) {
            $fields[] = $row['field_id'];
        }
        return $fields;
    }

    /**
     * Check if a specific field is hidden from Encounter Summary
     *
     * @param string $formId
     * @param string $fieldId
     * @return bool
     */
    public function isFieldHidden(string $formId, string $fieldId): bool
    {
        $this->ensureHiddenFieldsTable();
        $sql = "SELECT `is_hidden` FROM `module_group_canvas_hidden_fields` WHERE `form_id` = ? AND `field_id` = ? LIMIT 1";
        $row = sqlQuery($sql, [$formId, $fieldId]);
        return !empty($row['is_hidden']) && (int)$row['is_hidden'] === 1;
    }

    /**
     * Save or update hidden status for a field in a form
     *
     * @param string $formId
     * @param string $fieldId
     * @param bool $isHidden
     * @return bool
     */
    public function setFieldHidden(string $formId, string $fieldId, bool $isHidden): bool
    {
        $this->ensureHiddenFieldsTable();
        if ($isHidden) {
            $sql = "INSERT INTO `module_group_canvas_hidden_fields` (`form_id`, `field_id`, `is_hidden`)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE `is_hidden` = 1";
            return (bool)sqlStatement($sql, [$formId, $fieldId]);
        } else {
            $sql = "DELETE FROM `module_group_canvas_hidden_fields` WHERE `form_id` = ? AND `field_id` = ?";
            return (bool)sqlStatement($sql, [$formId, $fieldId]);
        }
    }

    /**
     * Get all hidden fields across all forms as an associative map: form_id => [field_id => true]
     *
     * @return array
     */
    public function getAllHiddenFields(): array
    {
        $this->ensureHiddenFieldsTable();
        $sql = "SELECT `form_id`, `field_id` FROM `module_group_canvas_hidden_fields` WHERE `is_hidden` = 1";
        $res = sqlStatement($sql);
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $fId = $row['form_id'];
            $fld = $row['field_id'];
            if (!isset($map[$fId])) {
                $map[$fId] = [];
            }
            $map[$fId][] = $fld;
        }
        return $map;
    }

    /**
     * Get hidden fields with field title and details for a form
     *
     * @param string $formId
     * @return array List of ['field_id' => ..., 'title' => ...]
     */
    public function getHiddenFieldsWithDetails(string $formId): array
    {
        $this->ensureHiddenFieldsTable();
        $sql = "SELECT h.field_id, l.title 
                FROM `module_group_canvas_hidden_fields` h 
                LEFT JOIN `layout_options` l ON l.form_id = h.form_id AND l.field_id = h.field_id 
                WHERE h.form_id = ? AND h.is_hidden = 1";
        $res = sqlStatement($sql, [$formId]);
        $fields = [];
        while ($row = sqlFetchArray($res)) {
            $fields[] = [
                'field_id' => $row['field_id'],
                'title' => $row['title'] ?? ''
            ];
        }
        return $fields;
    }

    /**
     * Get all hidden fields with title details across all forms: form_id => [ ['field_id' => ..., 'title' => ...], ... ]
     *
     * @return array
     */
    public function getAllHiddenFieldsWithTitles(): array
    {
        $this->ensureHiddenFieldsTable();
        $sql = "SELECT h.form_id, h.field_id, l.title 
                FROM `module_group_canvas_hidden_fields` h 
                LEFT JOIN `layout_options` l ON l.form_id = h.form_id AND l.field_id = h.field_id 
                WHERE h.is_hidden = 1";
        $res = sqlStatement($sql);
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $fId = $row['form_id'];
            if (!isset($map[$fId])) {
                $map[$fId] = [];
            }
            $map[$fId][] = [
                'field_id' => $row['field_id'],
                'title' => $row['title'] ?? ''
            ];
        }
        return $map;
    }
}


