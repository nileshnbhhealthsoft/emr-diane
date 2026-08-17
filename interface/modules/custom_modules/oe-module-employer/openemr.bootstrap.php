<?php

/*
 *
 * @package      OpenEMR
 * @link               https://www.open-emr.org
 *
 * @author    SNilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @copyright Copyright (c) 2021 Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Menu\PatientMenuEvent;
use OpenEMR\Events\PatientDemographics\RenderEvent as PatientDemographicsRenderEvent;
use OpenEMR\Events\Core\ScriptFilterEvent;
use OpenEMR\Events\Core\StyleFilterEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use OpenEMR\Common\Acl\AclMain;
use Juggernaut\OpenEMR\Modules\EmployerModule\Controller\EmployerDemographicsController;

// registering namespace
/**
 * @var ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('Juggernaut\\OpenEMR\\Modules\\EmployerModule\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');
// End of namespace registration

function oe_module_employer_add_menu_item(MenuEvent $event)
{
    $menu = $event->getMenu();

    // --- Reports > Insurance > Employers (existing) ---
    $reportMenuItem = new stdClass();
    $reportMenuItem->requirement = 0;
    $reportMenuItem->target = 'mod';
    $reportMenuItem->menu_id = 'mod_emp';
    $reportMenuItem->label = xlt("Employers");
    $reportMenuItem->url = "/interface/modules/custom_modules/oe-module-employer/public/reports/list_report.php";
    $reportMenuItem->children = [];
    $reportMenuItem->acl_req = ["patients", "docs"];
    $reportMenuItem->global_req = [];

    // --- Admin > Practice > Employers ---
    $adminMenuItem = new stdClass();
    $adminMenuItem->requirement = 0;
    $adminMenuItem->target = 'adm';
    $adminMenuItem->menu_id = 'mod_emp_admin';
    $adminMenuItem->label = xlt("Employers");
    $adminMenuItem->url = "/interface/modules/custom_modules/oe-module-employer/public/admin/employer_list.php";
    $adminMenuItem->children = [];
    $adminMenuItem->acl_req = ["admin", "practice"];
    $adminMenuItem->global_req = [];

    foreach ($menu as $item) {
        if ($item->menu_id == 'repimg') {
            foreach ($item->children as $childItem) {
                if ($childItem->label == 'Insurance') {
                    $childItem->children[] = $reportMenuItem;
                    break;
                }
            }
        }
        if ($item->menu_id == 'admimg') {
            foreach ($item->children as $childItem) {
                if (isset($childItem->label) && $childItem->label == 'Practice') {
                    $childItem->children[] = $adminMenuItem;
                    break;
                }
            }
        }
    }

    $event->setMenu($menu);

    return $event;
}

function oe_module_employer_patient_menu_item(PatientMenuEvent $menuEvent)
{
    $existingMenu = $menuEvent->getMenu();

    $menuItem = new stdClass();
    $menuItem->label = "Employers";
    $menuItem->url = OEGlobalsBag::getInstance()->getWebRoot() . "/interface/modules/custom_modules/oe-module-employer/public/index.php";
    $menuItem->menu_id = "mod_emp";
    $menuItem->target = "mod";

    $existingMenu[] = $menuItem;

    $menuEvent->setMenu($existingMenu);

    return $menuEvent;
}

function oe_module_employer_render_demographics(PatientDemographicsRenderEvent $event): void
{
    $controller = new EmployerDemographicsController();
    $controller->renderDemographicsSection($event);
}

function oe_module_employer_add_scripts(ScriptFilterEvent $event): void
{
    $pageName = $event->getPageName();
    $targetPages = ['demographics_full.php', 'demographics.php', 'new.php', 'new_comprehensive.php', 'index.php'];
    if (in_array($pageName, $targetPages)) {
        $scripts = $event->getScripts();
        $scripts[] = OEGlobalsBag::getInstance()->getWebRoot() . "/interface/modules/custom_modules/oe-module-employer/public/assets/js/employer-autocomplete.js";
        $event->setScripts($scripts);
    }
}

function oe_module_employer_add_styles(StyleFilterEvent $event): void
{
    $pageName = $event->getPageName();
    $targetPages = ['demographics_full.php', 'demographics.php', 'new.php', 'new_comprehensive.php', 'index.php'];
    if (in_array($pageName, $targetPages)) {
        $styles = $event->getStyles();
        $styles[] = OEGlobalsBag::getInstance()->getWebRoot() . "/interface/modules/custom_modules/oe-module-employer/public/assets/css/employer-autocomplete.css";
        $event->setStyles($styles);
    }
}

/**
 * @var EventDispatcherInterface $eventDispatcher
 * @var array                    $module
 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global                       $module          @see ModulesApplication::loadCustomModule
 */

$eventDispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_employer_add_menu_item');
$eventDispatcher->addListener(PatientMenuEvent::MENU_UPDATE, 'oe_module_employer_patient_menu_item');
$eventDispatcher->addListener(PatientDemographicsRenderEvent::EVENT_SECTION_LIST_RENDER_AFTER, 'oe_module_employer_render_demographics');
$eventDispatcher->addListener(ScriptFilterEvent::EVENT_NAME, 'oe_module_employer_add_scripts');
$eventDispatcher->addListener(StyleFilterEvent::EVENT_NAME, 'oe_module_employer_add_styles');
