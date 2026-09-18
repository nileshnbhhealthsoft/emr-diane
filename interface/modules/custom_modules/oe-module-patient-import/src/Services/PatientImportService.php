<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\PatientImport\Services;

use DateTime;
use Exception;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\Uuid;
use OpenEMR\Core\OEGlobalsBag;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PatientImportService
{
    /**
     * Checks if a database table exists.
     *
     * @param string $tableName
     * @return bool
     */
    public static function tableExists(string $tableName): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $res = sqlQuery("SELECT 1 AS x FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1", [$table]);
        return !empty($res);
    }

    /**
     * Generates a 16-byte binary UUID.
     *
     * @return string
     */
    public static function generateUuidBytes(): string
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class)) {
            return \Ramsey\Uuid\Uuid::uuid4()->getBytes();
        }
        return random_bytes(16);
    }

    /**
     * Definitions of the 16 mappable demographic fields.
     *
     * @return array
     */
    public static function getMappableFields(): array
    {
        return [
            'lname' => [
                'label' => 'Last Name',
                'required' => true,
                'aliases' => ['last name', 'lname', 'lastname', 'surname', 'family name', 'last', 'last_name', 'patient last name', 'last-name'],
                'example' => 'Again'
            ],
            'fname' => [
                'label' => 'First Name',
                'required' => true,
                'aliases' => ['first name', 'fname', 'firstname', 'given name', 'first', 'first_name', 'patient first name', 'first-name'],
                'example' => 'Dwight'
            ],
            'mname' => [
                'label' => 'Middle Initial',
                'required' => false,
                'aliases' => ['middle initial', 'mname', 'middle name', 'middlename', 'middle', 'middle_name', 'mi', 'm.i.', 'mid initial'],
                'example' => ''
            ],
            'street' => [
                'label' => 'Street 1',
                'required' => false,
                'aliases' => ['street 1', 'street1', 'street_1', 'street', 'address', 'address 1', 'address1', 'address line 1', 'address_line_1', 'street address'],
                'example' => '123 Abc'
            ],
            'street_line_2' => [
                'label' => 'Street 2',
                'required' => false,
                'aliases' => ['street 2', 'street2', 'street_2', 'street_line_2', 'address 2', 'address2', 'address line 2', 'address_line_2', 'apt', 'suite', 'unit'],
                'example' => ''
            ],
            'city' => [
                'label' => 'City',
                'required' => false,
                'aliases' => ['city', 'town', 'locality', 'municipality'],
                'example' => 'Phoenix'
            ],
            'state' => [
                'label' => 'State',
                'required' => false,
                'aliases' => ['state', 'st', 'state/province', 'province', 'region', 'state_code'],
                'example' => 'AZ'
            ],
            'postal_code' => [
                'label' => 'Zip Code',
                'required' => false,
                'aliases' => ['zip code', 'zipcode', 'zip', 'zip_code', 'postal code', 'postal_code', 'postal', 'pincode', 'postcode'],
                'example' => '85021'
            ],
            'phone_home' => [
                'label' => 'Phone 1',
                'required' => false,
                'aliases' => ['phone 1', 'phone1', 'phone_1', 'phone_home', 'home phone', 'home tel', 'home telephone', 'phone', 'telephone', 'phone number', 'phone_home_number'],
                'example' => '434-5777'
            ],
            'phone_cell' => [
                'label' => 'Phone 2',
                'required' => false,
                'aliases' => ['phone 2', 'phone2', 'phone_2', 'phone_cell', 'cell phone', 'mobile phone', 'mobile', 'cell', 'mobile_number', 'cell_number', 'cellphone'],
                'example' => ''
            ],
            'phone_biz' => [
                'label' => 'Phone 3',
                'required' => false,
                'aliases' => ['phone 3', 'phone3', 'phone_3', 'phone_biz', 'work phone', 'business phone', 'office phone', 'work telephone'],
                'example' => ''
            ],
            'phone_contact' => [
                'label' => 'Phone 4',
                'required' => false,
                'aliases' => ['phone 4', 'phone4', 'phone_4', 'phone_contact', 'emergency phone', 'contact phone', 'emergency contact phone'],
                'example' => ''
            ],
            'guardianphone' => [
                'label' => 'Phone 5',
                'required' => false,
                'aliases' => ['phone 5', 'phone5', 'phone_5', 'guardianphone', 'guardian phone', 'other phone', 'alternate phone', 'alt phone'],
                'example' => ''
            ],
            'ss' => [
                'label' => 'Social Security Number',
                'required' => false,
                'aliases' => ['social security number', 'ssn', 'ss', 'social security', 'ss#', 'social_security_number', 'ss_number', 'social security no', 'social_security'],
                'example' => '555-55-5557'
            ],
            'sex' => [
                'label' => 'Sex',
                'required' => false,
                'aliases' => ['sex', 'gender', 'sex/gender', 'm/f', 'patient sex', 'patient gender'],
                'example' => 'Male'
            ],
            'DOB' => [
                'label' => 'Date of Birth',
                'required' => false,
                'aliases' => ['date of birth', 'dob', 'birth date', 'birthdate', 'd.o.b.', 'd.o.b', 'birth_date', 'date_of_birth'],
                'example' => '08/31/1983'
            ],
        ];
    }

    /**
     * Returns the temporary upload directory path located in [sites]/<site_id>/documents/temp_upload.
     * Creates the directory if it does not already exist.
     *
     * @return string
     */
    public static function getTempUploadDir(): string
    {
        $siteDir = null;
        try {
            if (class_exists(OEGlobalsBag::class) && OEGlobalsBag::getInstance()->has('OE_SITE_DIR')) {
                $siteDir = OEGlobalsBag::getInstance()->get('OE_SITE_DIR');
            }
        } catch (\Throwable) {
            $siteDir = null;
        }

        if (!$siteDir && !empty($GLOBALS['OE_SITE_DIR'])) {
            $siteDir = $GLOBALS['OE_SITE_DIR'];
        }

        if (!$siteDir) {
            $siteId = $_SESSION['site_id'] ?? 'default';
            $siteDir = dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;
            if (!is_dir($siteDir)) {
                $siteDir = dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'default';
            }
        }

        $uploadDir = rtrim($siteDir, "\\/") . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'temp_upload';

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        return $uploadDir;
    }

    /**
     * Parses an uploaded CSV or Excel file and returns headers, sample preview, and temp file ID.
     *
     * @param string $uploadedFilePath
     * @param string $originalFilename
     * @return array
     */
    public static function parseUploadedFile(string $uploadedFilePath, string $originalFilename): array
    {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return [
                'success' => false,
                'message' => 'Invalid file format. Please upload a .csv, .xlsx, or .xls file.'
            ];
        }

        $uploadDir = self::getTempUploadDir();

        $tempKey = 'pat_import_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $savedPath = $uploadDir . DIRECTORY_SEPARATOR . $tempKey;

        if (!@copy($uploadedFilePath, $savedPath)) {
            return [
                'success' => false,
                'message' => 'Failed to store temporary upload file.'
            ];
        }

        $allRows = [];
        $headers = [];

        try {
            if ($ext === 'csv') {
                $fileHandle = fopen($savedPath, 'r');
                if ($fileHandle === false) {
                    throw new Exception('Could not open CSV file for reading.');
                }

                // Detect delimiter
                $firstLine = fgets($fileHandle);
                rewind($fileHandle);
                $delimiter = ',';
                if ($firstLine !== false) {
                    $delims = [',', "\t", ';', '|'];
                    $counts = [];
                    foreach ($delims as $d) {
                        $counts[$d] = count(str_getcsv($firstLine, $d));
                    }
                    arsort($counts);
                    $delimiter = array_key_first($counts) ?: ',';
                }

                // Read header
                $headerRow = fgetcsv($fileHandle, 0, $delimiter);
                if (!$headerRow) {
                    fclose($fileHandle);
                    throw new Exception('CSV file is empty or missing headers.');
                }

                // Strip UTF-8 BOM from first header
                $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headerRow[0]);

                foreach ($headerRow as $h) {
                    $headers[] = trim((string)$h);
                }

                while (($row = fgetcsv($fileHandle, 0, $delimiter)) !== false) {
                    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                        continue;
                    }
                    $allRows[] = array_map(fn($v) => trim((string)$v), $row);
                }
                fclose($fileHandle);
            } else {
                // Excel (.xlsx or .xls)
                $spreadsheet = IOFactory::load($savedPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $sheetData = $worksheet->toArray('', true, true, false);

                if (empty($sheetData)) {
                    throw new Exception('Excel sheet is empty.');
                }

                $headerRow = $sheetData[0] ?? [];
                foreach ($headerRow as $h) {
                    $headers[] = trim((string)$h);
                }

                $numRows = count($sheetData);
                for ($r = 1; $r < $numRows; $r++) {
                    $row = $sheetData[$r];
                    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                        continue;
                    }
                    $allRows[] = array_map(fn($v) => trim((string)$v), $row);
                }
            }
        } catch (\Throwable $e) {
            @unlink($savedPath);
            return [
                'success' => false,
                'message' => 'Error parsing file: ' . $e->getMessage()
            ];
        }

        // Cache parsed rows JSON next to file for faster execution
        $jsonCachePath = $savedPath . '.json';
        file_put_contents($jsonCachePath, json_encode([
            'headers' => $headers,
            'rows' => $allRows
        ]));

        $autoMapping = self::autoDetectMapping($headers);
        $previewRows = array_slice($allRows, 0, 5);

        return [
            'success' => true,
            'temp_key' => $tempKey,
            'filename' => $originalFilename,
            'total_rows' => count($allRows),
            'headers' => $headers,
            'preview_rows' => $previewRows,
            'auto_mapping' => $autoMapping,
            'fields' => self::getMappableFields()
        ];
    }

    /**
     * Smartly matches file headers to database field keys.
     *
     * @param array $headers
     * @return array [headerIndex => dbFieldKey]
     */
    public static function autoDetectMapping(array $headers): array
    {
        $mappable = self::getMappableFields();
        $mapping = [];
        $usedFields = [];

        foreach ($headers as $idx => $headerText) {
            $cleaned = strtolower(trim((string)$headerText));
            $cleanedNoPunct = preg_replace('/[^a-z0-9]/', '', $cleaned);

            $matchedField = null;

            foreach ($mappable as $fieldKey => $fieldDef) {
                if (in_array($fieldKey, $usedFields)) {
                    continue;
                }

                // Check direct key match
                if ($cleaned === strtolower($fieldKey) || $cleanedNoPunct === strtolower($fieldKey)) {
                    $matchedField = $fieldKey;
                    break;
                }

                // Check label match
                if ($cleaned === strtolower($fieldDef['label']) || $cleanedNoPunct === preg_replace('/[^a-z0-9]/', '', strtolower($fieldDef['label']))) {
                    $matchedField = $fieldKey;
                    break;
                }

                // Check aliases
                foreach ($fieldDef['aliases'] as $alias) {
                    if ($cleaned === strtolower($alias) || $cleanedNoPunct === preg_replace('/[^a-z0-9]/', '', strtolower($alias))) {
                        $matchedField = $fieldKey;
                        break 2;
                    }
                }
            }

            if ($matchedField !== null) {
                $mapping[$idx] = $matchedField;
                $usedFields[] = $matchedField;
            } else {
                $mapping[$idx] = '';
            }
        }

        return $mapping;
    }

    /**
     * Normalizes dates from various formats (MM/DD/YYYY, YYYY-MM-DD, MM-DD-YYYY, etc.) into YYYY-MM-DD.
     *
     * @param string|null $dateStr
     * @return string|null
     */
    public static function normalizeDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $trimmed = trim($dateStr);
        if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '00/00/0000') {
            return null;
        }

        // Check for Excel date serial number
        if (is_numeric($trimmed) && (float)$trimmed > 1000 && (float)$trimmed < 100000) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float)$trimmed);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                // Continue to string parsing
            }
        }

        // Try standard date formats
        $formats = [
            'Y-m-d',
            'm/d/Y',
            'n/j/Y',
            'm-d-Y',
            'n-j-Y',
            'd/m/Y',
            'j/n/Y',
            'd-m-Y',
            'j-n-Y',
            'Y/m/d',
            'Y/n/j',
            'Y.m.d',
            'm.d.Y',
            'M d, Y',
            'F d, Y',
            'd M Y',
            'd F Y'
        ];

        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $trimmed);
            if ($dt !== false && $dt->format($fmt) === $trimmed) {
                return $dt->format('Y-m-d');
            }
        }

        // Fallback to strtotime
        $timestamp = strtotime($trimmed);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Normalizes gender/sex values into OpenEMR standard format.
     *
     * @param string|null $sex
     * @return string
     */
    public static function normalizeSex(?string $sex): string
    {
        if (empty($sex)) {
            return '';
        }

        $s = strtolower(trim($sex));
        if ($s === 'm' || $s === 'male' || $s === 'man') {
            return 'Male';
        }
        if ($s === 'f' || $s === 'female' || $s === 'woman') {
            return 'Female';
        }
        if ($s === 'o' || $s === 'other' || $s === 'non-binary' || $s === 'transgender') {
            return 'Other';
        }
        if ($s === 'u' || $s === 'unknown') {
            return 'Unknown';
        }

        return ucfirst(trim($sex));
    }

    /**
     * Executes the patient demographics import.
     *
     * @param string $tempKey
     * @param array $columnMapping [headerIndex => dbFieldKey]
     * @param string $duplicateStrategy 'skip' | 'update' | 'create'
     * @param int|null $authUserId
     * @return array
     */
    public static function processImport(string $tempKey, array $columnMapping, string $duplicateStrategy = 'skip', ?int $authUserId = null): array
    {
        $uploadDir = self::getTempUploadDir();
        $jsonCachePath = $uploadDir . DIRECTORY_SEPARATOR . $tempKey . '.json';
        $originalFilePath = $uploadDir . DIRECTORY_SEPARATOR . $tempKey;

        if (!file_exists($jsonCachePath)) {
            return [
                'success' => false,
                'message' => 'Upload session expired or file not found. Please upload again.'
            ];
        }

        $data = json_decode(file_get_contents($jsonCachePath), true);
        if (!$data || empty($data['rows'])) {
            return [
                'success' => false,
                'message' => 'No data rows found in uploaded file.'
            ];
        }

        $rows = $data['rows'];
        $totalRows = count($rows);

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errorDetails = [];

        // Invert mapping: [fieldKey => columnIndex]
        $fieldToCol = [];
        foreach ($columnMapping as $colIdx => $fieldKey) {
            if (!empty($fieldKey)) {
                $fieldToCol[$fieldKey] = (int)$colIdx;
            }
        }

        // Must have at least fname or lname mapped
        if (!isset($fieldToCol['fname']) && !isset($fieldToCol['lname'])) {
            return [
                'success' => false,
                'message' => 'You must map at least First Name or Last Name column.'
            ];
        }

        $userId = $authUserId ?: 1;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-indexed including header
            try {
                // Extract mapped values for the 16 fields
                $record = [];
                foreach ($fieldToCol as $fieldKey => $colIdx) {
                    $record[$fieldKey] = isset($row[$colIdx]) ? trim((string)$row[$colIdx]) : '';
                }

                $lname = trim($record['lname'] ?? '');
                $fname = trim($record['fname'] ?? '');
                $mname = trim($record['mname'] ?? '');
                $street = trim($record['street'] ?? '');
                $street_line_2 = trim($record['street_line_2'] ?? '');
                $city = trim($record['city'] ?? '');
                $state = trim($record['state'] ?? '');
                $postal_code = trim($record['postal_code'] ?? '');
                $phone_home = trim($record['phone_home'] ?? '');
                $phone_cell = trim($record['phone_cell'] ?? '');
                $phone_biz = trim($record['phone_biz'] ?? '');
                $phone_contact = trim($record['phone_contact'] ?? '');
                $guardianphone = trim($record['guardianphone'] ?? '');
                $ss = trim($record['ss'] ?? '');
                $sex = self::normalizeSex($record['sex'] ?? '');
                $dob = self::normalizeDate($record['DOB'] ?? null);

                if ($fname === '' && $lname === '') {
                    $skippedCount++;
                    $errorDetails[] = [
                        'row' => $rowNum,
                        'name' => '(Blank Row)',
                        'status' => 'Skipped',
                        'reason' => 'Both First Name and Last Name are blank.'
                    ];
                    continue;
                }

                // Duplicate checking
                $existingPatient = null;

                if (!empty($ss)) {
                    $existingPatient = sqlQuery("SELECT id, pid, pubpid, fname, lname, DOB FROM patient_data WHERE ss = ? AND ss != '' LIMIT 1", [$ss]);
                }

                if (empty($existingPatient) && !empty($fname) && !empty($lname) && !empty($dob)) {
                    $existingPatient = sqlQuery(
                        "SELECT id, pid, pubpid, fname, lname, DOB FROM patient_data WHERE fname = ? AND lname = ? AND DOB = ? LIMIT 1",
                        [$fname, $lname, $dob]
                    );
                }

                if (!empty($existingPatient)) {
                    $existingPid = (int)$existingPatient['pid'];

                    if ($duplicateStrategy === 'skip') {
                        $skippedCount++;
                        $errorDetails[] = [
                            'row' => $rowNum,
                            'name' => "$fname $lname",
                            'status' => 'Skipped',
                            'reason' => "Duplicate patient already exists (PID: $existingPid)."
                        ];
                        continue;
                    } elseif ($duplicateStrategy === 'update') {
                        // Build dynamic update statement
                        $updateFields = [];
                        $bindings = [];

                        $fieldsToUpdate = [
                            'lname' => $lname,
                            'fname' => $fname,
                            'mname' => $mname,
                            'street' => $street,
                            'street_line_2' => $street_line_2,
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => $postal_code,
                            'phone_home' => $phone_home,
                            'phone_cell' => $phone_cell,
                            'phone_biz' => $phone_biz,
                            'phone_contact' => $phone_contact,
                            'guardianphone' => $guardianphone,
                            'ss' => $ss,
                            'sex' => $sex,
                            'DOB' => $dob,
                        ];

                        foreach ($fieldsToUpdate as $colName => $val) {
                            if ($val !== '' && $val !== null) {
                                $updateFields[] = "`$colName` = ?";
                                $bindings[] = $val;
                            }
                        }

                        if (!empty($updateFields)) {
                            $updateFields[] = "`last_updated` = NOW()";
                            $updateFields[] = "`updated_by` = ?";
                            $bindings[] = $userId;
                            $bindings[] = $existingPid;

                            $sql = "UPDATE patient_data SET " . implode(', ', $updateFields) . " WHERE pid = ?";
                            sqlStatement($sql, $bindings);
                        }

                        $updatedCount++;
                        $errorDetails[] = [
                            'row' => $rowNum,
                            'name' => "$fname $lname",
                            'status' => 'Updated',
                            'reason' => "Updated existing patient record (PID: $existingPid)."
                        ];
                        continue;
                    }
                }

                // Insert New Patient Record
                $maxPidRow = sqlQuery("SELECT COALESCE(MAX(pid), 0) + 1 AS next_pid FROM patient_data");
                $newPid = (int)($maxPidRow['next_pid'] ?? 1);
                $finalPubpid = (string)$newPid;
                $uuid = self::generateUuidBytes();

                $insertSql = "INSERT INTO patient_data (
                    `pid`, `uuid`, `pubpid`, `lname`, `fname`, `mname`,
                    `street`, `street_line_2`, `city`, `state`, `postal_code`,
                    `phone_home`, `phone_cell`, `phone_biz`, `phone_contact`, `guardianphone`,
                    `ss`, `sex`, `DOB`, `date`, `regdate`, `created_by`, `last_updated`
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, NOW(), NOW(), ?, NOW()
                )";

                $insertBindings = [
                    $newPid,
                    $uuid,
                    $finalPubpid,
                    $lname,
                    $fname,
                    $mname,
                    $street,
                    $street_line_2,
                    $city,
                    $state,
                    $postal_code,
                    $phone_home,
                    $phone_cell,
                    $phone_biz,
                    $phone_contact,
                    $guardianphone,
                    $ss,
                    $sex,
                    $dob,
                    $userId
                ];

                sqlStatement($insertSql, $insertBindings);
                $importedCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $errorDetails[] = [
                    'row' => $rowNum,
                    'name' => ($fname ?? '') . ' ' . ($lname ?? ''),
                    'status' => 'Error',
                    'reason' => $e->getMessage()
                ];
            }
        }

        // Clean up temp files
        @unlink($jsonCachePath);
        @unlink($originalFilePath);

        // Record log entry
        $logId = 0;
        if (self::tableExists('module_patient_import_logs')) {
            $logSql = "INSERT INTO module_patient_import_logs (
                `filename`, `file_type`, `total_rows`, `imported_count`, `updated_count`, `skipped_count`, `error_count`, `duplicate_mode`, `details_json`, `created_by`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $ext = pathinfo($tempKey, PATHINFO_EXTENSION);
            $logId = (int)sqlInsert($logSql, [
                $tempKey,
                $ext,
                $totalRows,
                $importedCount,
                $updatedCount,
                $skippedCount,
                $errorCount,
                $duplicateStrategy,
                json_encode(array_slice($errorDetails, 0, 100)),
                $userId
            ]);
        }

        return [
            'success' => true,
            'total_rows' => $totalRows,
            'imported' => $importedCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'error_details' => $errorDetails,
            'log_id' => $logId
        ];
    }

    /**
     * Generates a sample CSV template with the 16 demographic columns and sample records.
     *
     * @return string
     */
    public static function generateSampleCsv(): string
    {
        $fields = self::getMappableFields();
        $headers = [];
        foreach ($fields as $key => $def) {
            $headers[] = $def['label'];
        }

        $sampleRows = [
            [
                'Again', 'Dwight', '', '123 Abc', '', 'Phoenix', 'AZ', '85021', '434-5777', '', '', '', '', '555-55-5557', 'Male', '08/31/1983'
            ],
            [
                'Austin', 'Andrew', '', '1999 Allthe Way', '', 'Tempe', 'AZ', '85123', '767-2222', '', '', '', '', '999-88-7777', 'Male', '01-01-1950'
            ]
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleRows as $r) {
            fputcsv($output, $r);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Generates a sample Excel (.xlsx) spreadsheet template with the 16 demographic columns and sample records.
     *
     * @return void
     */
    public static function downloadSampleXlsx(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Patient Demographics');

        $fields = self::getMappableFields();
        $headers = [];
        foreach ($fields as $key => $def) {
            $headers[] = $def['label'];
        }

        // Header row
        $sheet->fromArray($headers, null, 'A1');

        // Style header row
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');

        $sampleRows = [
            [
                'Again', 'Dwight', '', '123 Abc', '', 'Phoenix', 'AZ', '85021', '434-5777', '', '', '', '', '555-55-5557', 'Male', '08/31/1983'
            ],
            [
                'Austin', 'Andrew', '', '1999 Allthe Way', '', 'Tempe', 'AZ', '85123', '767-2222', '', '', '', '', '999-88-7777', 'Male', '01-01-1950'
            ]
        ];

        $sheet->fromArray($sampleRows, null, 'A2');

        // Auto-fit columns
        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="patient_demographics_sample_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
