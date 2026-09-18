<?php

/**
 * openemr.bootstrap.php
 *
 * Bootstrap file for Patient Demographics Import module
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @var ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\PatientImport\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

function oe_module_patient_import_add_menu_item(MenuEvent $event): MenuEvent
{
    $menu = $event->getMenu();

    // --- Modules > Patient Demographics Import ---
    $importMenuItem = new stdClass();
    $importMenuItem->requirement = 0;
    $importMenuItem->target = 'mod';
    $importMenuItem->menu_id = 'mod_pat_import';
    $importMenuItem->label = function_exists('xlt') ? xlt("Patient Demographics Import") : "Patient Demographics Import";
    $importMenuItem->url = "/interface/modules/custom_modules/oe-module-patient-import/public/index.php";
    $importMenuItem->children = [];
    $importMenuItem->acl_req = ["admin", "practice"];
    $importMenuItem->global_req = [];

    foreach ($menu as $item) {
        if ($item->menu_id == 'modimg') {
            $item->children[] = $importMenuItem;
            break;
        }
    }

    $event->setMenu($menu);

    return $event;
}

if (isset($eventDispatcher) && $eventDispatcher instanceof EventDispatcherInterface) {
    $eventDispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_patient_import_add_menu_item');
} else {
    try {
        if (class_exists(OEGlobalsBag::class) && ($kernel = OEGlobalsBag::getInstance()->getKernel())) {
            $dispatcher = $kernel->getEventDispatcher();
            if ($dispatcher) {
                $dispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_patient_import_add_menu_item');
            }
        }
    } catch (\Throwable) {
        // Kernel not initialized or running in isolated scope
    }
}
