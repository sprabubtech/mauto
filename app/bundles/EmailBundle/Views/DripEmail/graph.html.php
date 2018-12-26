<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$label       = 'le.drip.email.lead.comparison';
$type        = 'line';
$dateFrom    = $dateRangeForm->children['date_from']->vars['data'];
$dateTo      = $dateRangeForm->children['date_to']->vars['data'];
$actionRoute = $view['router']->path('le_dripemail_campaign_action',
    [
        'objectAction' => 'view',
        'objectId'     => $entity->getId(),
        'daterange'    => [
            'date_to'   => $dateTo,
            'date_from' => $dateFrom,
        ],
    ]
);

?>
<div class="pa-md">
    <div class="row">
        <div class="col-sm-12">
            <div class="panel">
                <div class="panel-body box-layout">
                    <div class="col-xs-4 va-m">
                        <h5 class="text-white dark-md fw-sb mb-xs">
                            <span class="fa fa-envelope"></span>
                            <?php  echo $view['translator']->trans($label); ?>
                        </h5>
                    </div>
                    <div class="col-xs-8 va-m">
                        <?php echo $view->render('MauticCoreBundle:Helper:graph_dateselect.html.php', ['dateRangeForm' => $dateRangeForm, 'class' => 'pull-right']); ?>
                    </div>
                </div>
                <div class="row">
                    <?php if (!empty($stats)): ?>
                    <div class="pt-0 pl-15 pb-10 pr-15">
                        <?php echo $view->render('MauticCoreBundle:Helper:chart.html.php', ['chartData' => $stats, 'chartType' => $type, 'chartHeight' => 300]); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--/ some stats -->