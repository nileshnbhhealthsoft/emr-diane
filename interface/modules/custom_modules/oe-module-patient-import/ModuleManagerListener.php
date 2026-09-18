<?php

/**
 * Class to be called from Laminas Module Manager for reporting management actions.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\AbstractModuleActionListener;

class ModuleManagerListener extends AbstractModuleActionListener
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param        $methodName
     * @param        $modId
     * @param string $currentActionStatus
     * @return string
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($modId, $currentActionStatus);
        } else {
            return $currentActionStatus;
        }
    }

    /**
     * Required method to return namespace
     *
     * @return string
     */
    public static function getModuleNamespace(): string
    {
        return 'OpenEMR\\Modules\\PatientImport\\';
    }

    /**
     * Required method to return this class object
     *
     * @return ModuleManagerListener
     */
    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    /**
     * Executes the table.sql migration file to install or upgrade module database tables.
     *
     * @return bool
     */
    public static function runMigrations(): bool
    {
        $fullname = __DIR__ . DIRECTORY_SEPARATOR . 'table.sql';
        if (!file_exists($fullname)) {
            return false;
        }

        $fd = fopen($fullname, 'r');
        if ($fd === false) {
            return false;
        }

        $query = '';
        $skipping = false;

        try {
            while (!feof($fd)) {
                $line = fgets($fd, 2048);
                if ($line === false) {
                    break;
                }
                $line = rtrim($line);

                if (preg_match('/^\s*--/', $line) || $line === '') {
                    continue;
                }

                if (preg_match('/^#IfNotRow\s+(\S+)\s+(\S+)\s+(.+)/i', $line, $matches)) {
                    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[1]);
                    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[2]);
                    $val = trim($matches[3], "'\" \t");
                    $check = sqlQuery("SELECT 1 AS x FROM `$table` WHERE `$col` = ? LIMIT 1", [$val]);
                    $skipping = !empty($check);
                    continue;
                } elseif (preg_match('/^#IfNotTable\s+(\S+)/i', $line, $matches)) {
                    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[1]);
                    $check = sqlQuery("SELECT 1 AS x FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1", [$table]);
                    $skipping = !empty($check);
                    continue;
                } elseif (preg_match('/^#IfNotColumnType\s+(\S+)\s+(\S+)\s+(\S+)/i', $line, $matches)) {
                    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[1]);
                    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[2]);
                    $type = trim($matches[3]);
                    $colData = sqlQuery(
                        "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1",
                        [$table, $col]
                    );
                    $skipping = !empty($colData['COLUMN_TYPE']) && stripos((string)$colData['COLUMN_TYPE'], $type) !== false;
                    continue;
                } elseif (preg_match('/^#(EndIf|Endif)/i', $line)) {
                    $skipping = false;
                    continue;
                } elseif (preg_match('/^#/', $line)) {
                    continue;
                }

                if ($skipping) {
                    continue;
                }

                $query .= $line . "\n";
                if (preg_match('/;\s*$/', trim($line))) {
                    $stmt = rtrim(trim($query), "; \t\n\r");
                    if ($stmt !== '') {
                        sqlStatement($stmt);
                    }
                    $query = '';
                }
            }
        } catch (\Throwable $e) {
            error_log("Error running migrations for Patient Import module: " . $e->getMessage());
            fclose($fd);
            return false;
        }

        fclose($fd);
        return true;
    }

    private function help_requested($modId, $currentActionStatus): mixed
    {
        if (file_exists(__DIR__ . '/show_help.php')) {
            include __DIR__ . '/show_help.php';
        }
        return $currentActionStatus;
    }

    private function install($modId, $currentActionStatus): mixed
    {
        self::runMigrations();
        return $currentActionStatus;
    }

    private function enable($modId, $currentActionStatus): mixed
    {
        self::runMigrations();
        return $currentActionStatus;
    }

    private function install_sql($modId, $currentActionStatus): mixed
    {
        self::runMigrations();
        return $currentActionStatus;
    }

    private function upgrade_sql($modId, $currentActionStatus): mixed
    {
        self::runMigrations();
        return $currentActionStatus;
    }

    private function disable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function unregister($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }
}
