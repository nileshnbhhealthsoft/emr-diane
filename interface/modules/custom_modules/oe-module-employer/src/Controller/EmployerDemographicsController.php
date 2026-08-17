<?php

/*
 * package   OpenEMR
 * link      https://www.open-emr.org
 * author    Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * Copyright (c) 2024 Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 * license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\OpenEMR\Modules\EmployerModule\Controller;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\PatientDemographics\RenderEvent as PatientDemographicsRenderEvent;

class EmployerDemographicsController
{
    /**
     * Renders the Practice Employers card section inside the Patient Demographics screen.
     *
     * @param PatientDemographicsRenderEvent $event
     * @return void
     */
    public function renderDemographicsSection(PatientDemographicsRenderEvent $event): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $pid = $event->getPid() ?: ($session->get('pid') ?? null);

        if (empty($pid)) {
            return;
        }

        // Check if user has permission to view patient demographics
        if (!AclMain::aclCheckCore('patients', 'demo')) {
            return;
        }

        $canEdit = AclMain::aclCheckCore('admin', 'practice') || AclMain::aclCheckCore('patients', 'demo', '', 'write');
        $manageUrl = OEGlobalsBag::getInstance()->getWebRoot() . "/interface/modules/custom_modules/oe-module-employer/public/admin/employer_list.php";

        // Fetch employer data from practice_employers table
        $employers = $this->getPracticeEmployers();
        $count = count($employers);

        $widgetId = "oe_employer_ps_expand";
        $initiallyCollapsed = (function_exists('getUserSetting') && getUserSetting($widgetId) == 0);

        ?>
        <section class="card mb-2 oe-employer-widget-card">
            <div class="card-body p-1">
                <h6 class="card-title mb-0 d-flex p-1 justify-content-between align-items-center">
                    <a class="text-left font-weight-bolder text-decoration-none text-dark d-flex align-items-center"
                       href="javascript:void(0);"
                       data-toggle="collapse"
                       data-target="#<?php echo attr($widgetId); ?>"
                       aria-expanded="<?php echo $initiallyCollapsed ? 'false' : 'true'; ?>"
                       aria-controls="<?php echo attr($widgetId); ?>"
                       onclick="if(typeof toggleIndicator === 'function'){ toggleIndicator(this, '<?php echo attr_js($widgetId); ?>'); }">
                        <i class="fa fa-briefcase text-primary mr-1"></i>
                        <span><?php echo xlt("Employers"); ?></span>
                        <?php if ($count > 0): ?>
                            <span class="badge badge-primary badge-pill ml-2" style="font-size: 0.75rem;"><?php echo text($count); ?></span>
                        <?php endif; ?>
                        <i class="ml-2 fa fa-fw <?php echo $initiallyCollapsed ? 'fa-expand' : 'fa-compress'; ?>" data-target="#<?php echo attr($widgetId); ?>"></i>
                    </a>
                    <?php if ($canEdit): ?>
                        <span>
                            <a class="btn btn-outline-primary btn-sm py-0 px-2 font-weight-bold"
                               href="<?php echo attr($manageUrl); ?>"
                               onclick="if(top && top.restoreSession){ top.restoreSession(); }"
                               title="<?php echo xla('Manage Practice Employers'); ?>">
                                <i class="fa fa-plus fa-sm mr-1"></i><?php echo xlt("Manage"); ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </h6>

                <div id="<?php echo attr($widgetId); ?>" class="card-text collapse <?php echo $initiallyCollapsed ? '' : 'show'; ?>">
                    <div class="clearfix pt-2 px-1">
                        <?php if ($count > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover mb-1">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col" style="width: 30%;"><?php echo xlt("Employer / Company"); ?></th>
                                            <th scope="col" style="width: 25%;"><?php echo xlt("Phone"); ?></th>
                                            <th scope="col" style="width: 35%;"><?php echo xlt("Address"); ?></th>
                                            <?php if ($canEdit): ?>
                                                <th scope="col" class="text-right" style="width: 10%;"><?php echo xlt("Action"); ?></th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employers as $emp): ?>
                                            <tr>
                                                <td class="align-middle">
                                                    <strong><?php echo text($emp['name']); ?></strong>
                                                </td>
                                                <td class="align-middle">
                                                    <?php if (!empty($emp['phone'])): ?>
                                                        <i class="fa fa-phone fa-xs text-muted mr-1"></i><?php echo text($emp['phone']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="align-middle">
                                                    <small><?php echo text($emp['formatted_address'] ?: '-'); ?></small>
                                                </td>
                                                <?php if ($canEdit): ?>
                                                    <td class="align-middle text-right">
                                                        <a href="<?php echo attr($manageUrl); ?>"
                                                           onclick="if(top && top.restoreSession){ top.restoreSession(); }"
                                                           class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                           title="<?php echo xla('Edit employer'); ?>">
                                                            <i class="fa fa-pencil-alt fa-xs"></i>
                                                        </a>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center py-2 px-1 text-muted">
                                <div>
                                    <i class="fa fa-info-circle mr-1"></i>
                                    <span><?php echo xlt("No employer records on file in practice employers."); ?></span>
                                </div>
                                <?php if ($canEdit): ?>
                                    <a href="<?php echo attr($manageUrl); ?>"
                                       onclick="if(top && top.restoreSession){ top.restoreSession(); }"
                                       class="btn btn-outline-primary btn-sm py-0 px-2">
                                        <i class="fa fa-plus fa-sm mr-1"></i><?php echo xlt("Add Employer"); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Fetches and prepares employer records from the practice_employers table.
     *
     * @return array
     */
    public function getPracticeEmployers(): array
    {
        $sql = "SELECT * FROM `practice_employers` ORDER BY `name` ASC";
        $result = sqlStatement($sql);

        $employers = [];
        if ($result) {
            while ($row = sqlFetchArray($result)) {
                // Format address
                $addressParts = [];
                if (!empty($row['street'])) {
                    $addressParts[] = $row['street'];
                }
                if (!empty($row['street_line_2'])) {
                    $addressParts[] = $row['street_line_2'];
                }
                $cityStateZip = [];
                if (!empty($row['city'])) {
                    $cityStateZip[] = $row['city'];
                }
                if (!empty($row['state'])) {
                    $cityStateZip[] = $row['state'];
                }
                if (!empty($row['postal_code'])) {
                    $cityStateZip[] = $row['postal_code'];
                }
                if (!empty($cityStateZip)) {
                    $addressParts[] = implode(', ', $cityStateZip);
                }
                if (!empty($row['country'])) {
                    $addressParts[] = $row['country'];
                }

                $employers[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                    'phone' => (string)($row['phone'] ?? ''),
                    'street' => (string)($row['street'] ?? ''),
                    'street_line_2' => (string)($row['street_line_2'] ?? ''),
                    'city' => (string)($row['city'] ?? ''),
                    'state' => (string)($row['state'] ?? ''),
                    'postal_code' => (string)($row['postal_code'] ?? ''),
                    'country' => (string)($row['country'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'formatted_address' => implode('; ', $addressParts)
                ];
            }
        }

        return $employers;
    }
}
