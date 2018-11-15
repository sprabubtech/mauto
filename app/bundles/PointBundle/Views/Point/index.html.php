<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('leContent', 'point');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.points.menu.root'));

$view['slots']->set(
    'actions',
    $view->render(
        'MauticCoreBundle:Helper:page_actions.html.php',
        [
            'templateButtons' => [
                'new' => $permissions['point:points:create'],
            ],
            'routeBase' => 'point',
        ]
    )
);
?>
<div class="le-header-align"><h3><?php echo $view['translator']->trans('mautic.point.menu.index'); ?></h3></div>
<div style="padding-top: 15px;">
    <?php foreach ($pointsBlockDetails as $key => $pointsBlock): ?>
        <div class="info-box" id="leads-info-box-container">
                <span class="info-box-icon" style="background-color:<?php echo $pointsBlock[0]; ?>;>">
                    <i class="<?php echo $pointsBlock[1]; ?>" id="icon-class-leads"></i></span>
            <div class="info-box-content">
                <span class="info-box-text"><?php echo $pointsBlock[2]; ?></span>
                <span class="info-box-number"><?php echo $pointsBlock[3]; ?></span>
            </div>

        </div>
    <?php endforeach; ?>
</div>
<div class="panel panel-default bdr-t-wdh-0 mb-0">
    <?php echo $view->render(
        'MauticCoreBundle:Helper:list_toolbar.html.php',
        [
            'searchValue' => $searchValue,
            'searchHelp'  => 'mautic.core.help.searchcommands',
            'action'      => $currentRoute,
        ]
    ); ?>
    <div class="page-list">
        <?php $view['slots']->output('_content'); ?>
    </div>
</div>
