<?php

/**
 * GroupCanvasDataModel.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Antigravity AI
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\GroupCanvas\Model;

class GroupCanvasDataModel
{
    /**
     * Get drawing data for a specific patient, encounter, form, and group
     * Robust matching with fallback between form_instance_id > 0 and form_instance_id = 0
     *
     * @param int $pid
     * @param int $encounter
     * @param string $formId
     * @param string $groupId
     * @param int $formInstanceId
     * @return array|null
     */
    public function getDrawing(
        int $pid,
        int $encounter,
        string $formId,
        string $groupId,
        int $formInstanceId = 0
    ): ?array {
        if ($pid <= 0 || empty($formId) || empty($groupId)) {
            return null;
        }

        // 1. If formInstanceId is specified, try exact match first
        if ($formInstanceId > 0) {
            $sql = "SELECT * FROM `module_group_canvas_data` 
                    WHERE `pid` = ? AND `encounter` = ? AND `form_id` = ? AND `group_id` = ? AND `form_instance_id` = ?
                    ORDER BY `updated_at` DESC LIMIT 1";
            $row = sqlQuery($sql, [$pid, $encounter, $formId, $groupId, $formInstanceId]);
            if (!empty($row)) {
                return $row;
            }
        }

        // 2. Fallback: match by pid, encounter, form_id, group_id (latest updated)
        $sql = "SELECT * FROM `module_group_canvas_data` 
                WHERE `pid` = ? AND `encounter` = ? AND `form_id` = ? AND `group_id` = ?
                ORDER BY `updated_at` DESC LIMIT 1";
        $row = sqlQuery($sql, [$pid, $encounter, $formId, $groupId]);
        if (!empty($row)) {
            return $row;
        }

        // 3. Fallback for patient-level forms or cross-instance lookup if encounter is 0
        if ($encounter > 0) {
            $sql = "SELECT * FROM `module_group_canvas_data` 
                    WHERE `pid` = ? AND `encounter` = 0 AND `form_id` = ? AND `group_id` = ?
                    ORDER BY `updated_at` DESC LIMIT 1";
            $row = sqlQuery($sql, [$pid, $formId, $groupId]);
            if (!empty($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Get all drawings saved for a given encounter and form
     *
     * @param int $pid
     * @param int $encounter
     * @param string $formId
     * @return array Hash map of group_id => row
     */
    public function getDrawingsForEncounter(int $pid, int $encounter, string $formId): array
    {
        if ($pid <= 0 || empty($formId)) {
            return [];
        }

        $sql = "SELECT * FROM `module_group_canvas_data` 
                WHERE `pid` = ? AND (`encounter` = ? OR `encounter` = 0) AND `form_id` = ? 
                ORDER BY `updated_at` DESC";
        $res = sqlStatement($sql, [$pid, $encounter, $formId]);
        $drawings = [];
        while ($row = sqlFetchArray($res)) {
            $groupId = $row['group_id'];
            if (!isset($drawings[$groupId])) {
                $drawings[$groupId] = $row;
            }
        }
        return $drawings;
    }

    /**
     * Get all drawings for an encounter across all forms (for Visit Summary)
     *
     * @param int $pid
     * @param int $encounter
     * @return array Hash map of form_id => [group_id => row]
     */
    public function getAllDrawingsForEncounter(int $pid, int $encounter): array
    {
        if ($pid <= 0) {
            return [];
        }

        $sql = "SELECT * FROM `module_group_canvas_data` 
                WHERE `pid` = ? AND (`encounter` = ? OR `encounter` = 0) 
                ORDER BY `updated_at` DESC";
        $res = sqlStatement($sql, [$pid, $encounter]);
        $drawings = [];
        while ($row = sqlFetchArray($res)) {
            $fId = $row['form_id'];
            $gId = $row['group_id'];
            if (!isset($drawings[$fId])) {
                $drawings[$fId] = [];
            }
            if (!isset($drawings[$fId][$gId])) {
                $drawings[$fId][$gId] = $row;
            }
        }
        return $drawings;
    }

    /**
     * Save or update drawing data
     *
     * @param int $pid
     * @param int $encounter
     * @param string $formId
     * @param string $groupId
     * @param string|null $drawingData JSON vector strokes
     * @param string|null $drawingPng Base64 PNG
     * @param int $userId
     * @param int $formInstanceId
     * @return int Inserted or updated record ID
     */
    public function saveDrawing(
        int $pid,
        int $encounter,
        string $formId,
        string $groupId,
        ?string $drawingData,
        ?string $drawingPng,
        int $userId = 0,
        int $formInstanceId = 0
    ): int {
        $existing = $this->getDrawing($pid, $encounter, $formId, $groupId, $formInstanceId);

        if ($existing) {
            $targetInstanceId = $formInstanceId > 0 ? $formInstanceId : (int)($existing['form_instance_id'] ?? 0);
            $targetEncounter = $encounter > 0 ? $encounter : (int)($existing['encounter'] ?? 0);

            $sql = "UPDATE `module_group_canvas_data` 
                    SET `drawing_data` = ?, 
                        `drawing_png` = ?, 
                        `form_instance_id` = ?,
                        `encounter` = ?,
                        `updated_by` = ?, 
                        `updated_at` = NOW() 
                    WHERE `id` = ?";
            sqlStatement($sql, [$drawingData, $drawingPng, $targetInstanceId, $targetEncounter, $userId, $existing['id']]);
            return (int)$existing['id'];
        } else {
            $sql = "INSERT INTO `module_group_canvas_data` 
                    (`pid`, `encounter`, `form_id`, `form_instance_id`, `group_id`, `drawing_data`, `drawing_png`, `created_by`, `updated_by`, `created_at`, `updated_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            return (int)sqlInsert($sql, [
                $pid,
                $encounter,
                $formId,
                $formInstanceId,
                $groupId,
                $drawingData,
                $drawingPng,
                $userId,
                $userId
            ]);
        }
    }
}
