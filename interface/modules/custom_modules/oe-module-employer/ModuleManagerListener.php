<?php

/**
 * Class to be called from Laminas Module Manager for reporting management actions.
 * Example is if the module is enabled, disabled or unregistered etc.
 *
 * The class is in the Laminas "Installer\Controller" namespace.
 * Currently, register isn't supported of which support should be a part of install.
 * If an error needs to be reported to user, return description of error.
 * However, whatever action trapped here has already occurred in Manager.
 * Catch any exceptions because chances are they will be overlooked in Laminas module.
 * Report them in the return value.
 *
 * @package   OpenEMR Modules
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @copyright Copyright (c) 2024 Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\AbstractModuleActionListener;

/* Allows maintenance of background tasks depending on Module Manager action. */

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
     * @return string On method success a $currentAction status should be returned or error string.
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($modId, $currentActionStatus);
        } else {
            // no reason to report, action method is missing.
            return $currentActionStatus;
        }
    }

    /**
     * Required method to return namespace
     * If namespace isn't provided return empty string
     * and register namespace at top of this script..
     *
     * @return string
     */
    public static function getModuleNamespace(): string
    {
        // Module Manager will register this namespace.
        return 'Juggernaut\\OpenEMR\\Modules\\EmployerModule\\';
    }

    /**
     * Required method to return this class object
     * so it will be instantiated in Laminas Manager.
     *
     * @return ModuleManagerListener
     */
    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function help_requested($modId, $currentActionStatus): mixed
    {
        // must call a script that implements a dialog to show help.
        // I can't find a way to override the Lamina's UI except using a dialog.
        if (file_exists(__DIR__ . '/show_help.php')) {
            include __DIR__ . '/show_help.php';
        }
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function enable($modId, $currentActionStatus): mixed
    {
        // Return the current action status from Module Manager in case of error from its action.
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function disable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function unregister($modId, $currentActionStatus)
    {
        $records = sqlQuery("SELECT * FROM `employer_data` ORDER BY id DESC LIMIT 1");
        if (empty($records)) {
            $sql = "DROP TABLE IF EXISTS `employer_data`";
            sqlStatement($sql);
            ServiceContainer::getLogger()->info('Employer table has been left in place; remove manually if desired.');
        }
        return $currentActionStatus;
    }
}