<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if ($tmpl == 'index'):
    $view->extend('MauticLeadBundle:Import:index.html.php');
endif;
$isAdmin=$view['security']->isAdmin();
?>

<?php if (count($items)): ?>
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered" id="importTable">
        <thead>
            <tr>
                <?php
                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.status',
                    'text'       => 'le.lead.import.status',
                    'class'      => 'col-status',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.originalFile',
                    'text'       => 'le.lead.import.source.file',
                    'class'      => 'col-original-file',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'text'  => 'le.lead.import.runtime',
                    'class' => 'col-runtime',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'text'  => 'le.lead.import.progress',
                    'class' => 'col-progress',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.lineCount',
                    'text'       => 'le.lead.import.line.count',
                    'class'      => 'col-line-count',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.insertedCount',
                    'text'       => 'le.lead.import.inserted.count',
                    'class'      => 'col-inserted-count',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.updatedCount',
                    'text'       => 'le.lead.import.updated.count',
                    'class'      => 'col-updated-count',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.ignoredCount',
                    'text'       => 'le.lead.import.ignored.count',
                    'class'      => 'col-ignored-count',
                ]);

                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.dateAdded',
                    'text'       => 'mautic.core.date.added',
                    'class'      => 'col-date-added visible-md visible-lg',
                    'default'    => true,
                ]);
                if ($isAdmin):
                echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', [
                    'sessionVar' => $sessionVar,
                    'orderBy'    => $tablePrefix.'.id',
                    'text'       => 'mautic.core.id',
                    'class'      => 'col-lead-id visible-md visible-lg',
                ]);
                endif;
                ?>
            </tr>
        </thead>
        <tbody>
        <?php echo $view->render('MauticLeadBundle:Import:list_rows.html.php', [
            'items'           => $items,
            'permissions'     => $permissions,
            'indexRoute'      => $indexRoute,
            'permissionBase'  => $permissionBase,
            'translationBase' => $translationBase,
            'actionRoute'     => $actionRoute,
        ]); ?>
        </tbody>
    </table>
</div>
<div class="panel-footer">
    <?php echo $view->render('MauticCoreBundle:Helper:pagination.html.php', [
        'totalItems' => $totalItems,
        'page'       => $page,
        'limit'      => $limit,
        'menuLinkId' => $indexRoute,
        'baseUrl'    => $view['router']->path($indexRoute, ['object' => $app->getRequest()->get('object', 'contacts')]),
        'sessionVar' => $sessionVar,
    ]); ?>
</div>
<?php else: ?>
<?php echo $view->render('MauticCoreBundle:Helper:noresults.html.php'); ?>
<?php endif; ?>
