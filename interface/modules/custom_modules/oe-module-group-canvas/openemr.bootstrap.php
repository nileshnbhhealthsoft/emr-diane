<?php

/**
 * openemr.bootstrap.php
 *
 * Bootstrap file for Group Canvas & Annotation module
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Events\Core\ScriptFilterEvent;
use OpenEMR\Events\Core\StyleFilterEvent;
use OpenEMR\Events\Encounter\EncounterFormsListRenderEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @var ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\GroupCanvas\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

/**
 * Inject module scripts into layout forms and layout editor
 */
function oe_module_group_canvas_add_scripts(ScriptFilterEvent $event): void
{
    $pageName = basename((string)$event->getPageName());
    $webroot = OEGlobalsBag::getInstance()->getWebRoot();
    $scripts = $event->getScripts();

    // 1. Layout Editor (Administration > Layouts)
    if ($pageName === 'edit_layout.php') {
        $editorJs = __DIR__ . '/public/assets/js/group-canvas-layout-editor.js';
        $v = file_exists($editorJs) ? filemtime($editorJs) : time();
        $scripts[] = $webroot . "/interface/modules/custom_modules/oe-module-group-canvas/public/assets/js/group-canvas-layout-editor.js?v=" . $v;
        $event->setScripts($scripts);
        return;
    }

    // 2. Clinical Encounter Forms
    $targetPages = [
        'new.php',
        'view.php',
        'view_form.php',
        'load_form.php',
        'forms.php',
        'trend_form.php',
        'demographics.php',
        'demographics_full.php',
        'history.php',
        'history_full.php',
        'new_comprehensive.php',
        'printable.php',
        'report.php'
    ];

    if (in_array($pageName, $targetPages)) {
        $authJs = __DIR__ . '/public/api/auth_context.js.php';
        $drawerJs = __DIR__ . '/public/assets/js/group-canvas-drawer.js';
        $injectorJs = __DIR__ . '/public/assets/js/group-canvas-injector.js';
        $vAuth = file_exists($authJs) ? filemtime($authJs) : time();
        $vDrawer = file_exists($drawerJs) ? filemtime($drawerJs) : time();
        $vInjector = file_exists($injectorJs) ? filemtime($injectorJs) : time();
        $scripts[] = $webroot . "/interface/modules/custom_modules/oe-module-group-canvas/public/api/auth_context.js.php?v=" . $vAuth;
        $scripts[] = $webroot . "/interface/modules/custom_modules/oe-module-group-canvas/public/assets/js/group-canvas-drawer.js?v=" . $vDrawer;
        $scripts[] = $webroot . "/interface/modules/custom_modules/oe-module-group-canvas/public/assets/js/group-canvas-injector.js?v=" . $vInjector;
        $event->setScripts($scripts);
    }
}

/**
 * Inject module styles into layout forms and layout editor
 */
function oe_module_group_canvas_add_styles(StyleFilterEvent $event): void
{
    $pageName = basename((string)$event->getPageName());
    $targetPages = [
        'edit_layout.php',
        'new.php',
        'view.php',
        'view_form.php',
        'load_form.php',
        'forms.php',
        'trend_form.php',
        'demographics.php',
        'demographics_full.php',
        'history.php',
        'history_full.php',
        'new_comprehensive.php',
        'printable.php',
        'report.php'
    ];

    if (in_array($pageName, $targetPages)) {
        $webroot = OEGlobalsBag::getInstance()->getWebRoot();
        $cssPath = __DIR__ . '/public/assets/css/group-canvas.css';
        $vCss = file_exists($cssPath) ? filemtime($cssPath) : time();
        $styles = $event->getStyles();
        $styles[] = $webroot . "/interface/modules/custom_modules/oe-module-group-canvas/public/assets/css/group-canvas.css?v=" . $vCss;
        $event->setStyles($styles);
    }
}

/**
 * Pre-render event listener for Encounter Summary (forms.php)
 * Injects hidden fields data for client-side exclusion
 */
function oe_module_group_canvas_encounter_forms_render_pre(EncounterFormsListRenderEvent $event): void
{
    try {
        $configModel = new \OpenEMR\Modules\GroupCanvas\Model\GroupCanvasConfigModel();
        $allHidden = $configModel->getAllHiddenFieldsWithTitles();
        if (!empty($allHidden)) {
            echo "<script>window.oeGroupCanvasHiddenFieldsMap = " . json_encode($allHidden) . ";</script>\n";
        }
    } catch (\Throwable $e) {
        // Fail-safe
    }
}

/**
 * @var EventDispatcherInterface $eventDispatcher
 * @var array                    $module
 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global                       $module          @see ModulesApplication::loadCustomModule
 */
$eventDispatcher->addListener(ScriptFilterEvent::EVENT_NAME, 'oe_module_group_canvas_add_scripts');
$eventDispatcher->addListener(StyleFilterEvent::EVENT_NAME, 'oe_module_group_canvas_add_styles');
$eventDispatcher->addListener(EncounterFormsListRenderEvent::EVENT_SECTION_RENDER_PRE, 'oe_module_group_canvas_encounter_forms_render_pre');

