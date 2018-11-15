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

$object = $app->getRequest()->get('object', 'leads');

$view['slots']->set('mauticContent', 'leadImport');
$view['slots']->set('headerTitle', $view['translator']->trans('le.lead.import.leads', ['%object%' => $object]));

$percent    = $progress->toPercent();
$id         = ($complete) ? 'leadImportProgressComplete' : 'leadImportProgress';
$header     = ($complete) ? 'le.lead.import.success' : 'le.lead.import.donotleave';
$indexRoute = $object === 'leads' ? 'le_contact_index' : 'le_company_index';
?>

<div class="row ma-lg" id="<?php echo $id; ?>">
    <div class="col-sm-offset-3 col-sm-6 text-center">
        <div class="panel panel-<?php echo ($complete) ? 'success' : 'danger'; ?>">
            <div class="panel-heading">

                <h4 class="panel-title"><?php echo $view['translator']->trans($header, ['object' => $object]); ?></h4>
            </div>
            <div class="panel-body">
                <?php if (!$complete): ?>
                    <h4><?php echo $view['translator']->trans('le.lead.import.inprogress'); ?></h4>
                <?php else: ?>
                    <h4>
                        <?php if ($import->getIgnoredCount() == 1 || $import->getIgnoredCount() == 0) {
    $ignoredCount='0';
} else {
    $ignoredCount= $import->getIgnoredCount() - 1;
}
                           echo $view['translator']->trans(
                               'le.lead.import.stats',
                                [
                               '%merged%'  => $import->getUpdatedCount(),
                               '%created%' => $import->getInsertedCount(),
                               '%ignored%' => $ignoredCount,
                                ]
                           ); ?>
                    </h4>
                <?php endif; ?>
                <div class="progress mt-md" style="height:50px;">
                    <div class="progress-bar-import progress-bar progress-bar-striped<?php if (!$complete) {
                               echo ' active';
                           } ?>" role="progressbar" aria-valuenow="<?php echo $progress->getDone(); ?>" aria-valuemin="0" aria-valuemax="<?php echo $progress->getTotal(); ?>" style="width: <?php echo $percent; ?>%; height: 50px;background-color: #ec407a">
                        <span class="sr-only"><?php echo $percent; ?>%</span>
                    </div>
                </div>
            </div>
            <?php if (!empty($failedRows)): ?>
                <ul class="list-group">
                    <?php foreach ($failedRows as $row): ?>
                        <?php $lineNumber = isset($row['properties']['line']) ? $row['properties']['line'] : 'N/A'; ?>
                        <?php $failure    = isset($row['properties']['error']) ? $row['properties']['error'] : 'N/A'; ?>
                        <li class="list-group-item text-left">
                            <a target="_new" class="text-danger">
                                <?php echo "(#$lineNumber) $failure"; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="panel-footer">
                <p class="small"><span class="imported-count"><?php echo empty($progress->getDone()) ? '0' : $progress->getDone() - 1; ?></span> / <span class="total-count"><?php echo $progress->getTotal() - 1; ?></span></p>
                <?php if (!$complete): ?>
                    <div>
                        <a class="btn btn-danger" href="<?php echo $view['router']->path(
                            'le_contact_import_action',
                            ['objectAction' => 'cancel']
                        ); ?>" data-toggle="ajax">
                            <?php echo $view['translator']->trans('mautic.core.form.cancel'); ?>
                        </a>
                        <a class="btn btn-primary" href="<?php echo $view['router']->path(
                            'le_contact_import_action',
                            ['objectAction' => 'queue']
                        ); ?>" data-toggle="ajax">
                            <?php echo $view['translator']->trans('le.lead.import.queue.btn'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div>
                        <a class="btn btn-success mb-5" href="<?php echo $view['router']->path($indexRoute); ?>" data-toggle="ajax">
                            <?php echo $view['translator']->trans('le.lead.list.view_'.$object); ?>
                        </a>
                        <a class="btn btn-success mb-5" href="<?php echo $view['router']->path('le_import_index', ['object' => $object]); ?>" data-toggle="ajax">
                            <?php echo $view['translator']->trans('le.lead.view.imports'); ?>
                        </a>
                        <br>
                        <a class="btn btn-success" href="<?php echo $view['router']->path(
                            'le_import_action',
                            ['objectAction' => 'view', 'objectId' => $import->getId(), 'object' => $object]
                        ); ?>" data-toggle="ajax">
                            <?php echo $view['translator']->trans('le.lead.import.result.info', ['%import%' => $import->getName()]); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
