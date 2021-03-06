<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:FormTheme:form_simple.html.php');
$view->addGlobal('translationBase', 'mautic.slack');
$view->addGlobal('leContent', 'slack');
$isAdmin    =$view['security']->isAdmin();
?>

<?php $view['slots']->start('primaryFormContent'); ?>
<div class="panel panel-default form-group mb-0">
    <div class="panel-body">
<div class="row">
    <div class="col-md-6">
        <?php echo $view['form']->row($form['name']); ?>
    </div>
    <div class="col-md-6">
        <?php echo $view['form']->row($form['category']); ?>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <li class="dropdown" style="display: block;">
            <a class="btn btn-nospin btn-primary btn-sm hidden-xs  " style="font-size: 14px;position:relative;left:88.7%;margin-bottom:-2%;vertical-align:super;z-index: 10000;"  data-toggle="dropdown" href="#">
                <span><?php echo $view['translator']->trans('le.core.personalize.button'); ?></span> </span><span><i class="caret" ></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-right">
                <li>
                    <div class="insert-tokens" style="background-color: whitesmoke;overflow-y: scroll;max-height: 154px;">
                    </div>
                </li>
            </ul>
        </li>
        <div style="margin-top: -20px">
        <?php echo $view['form']->row($form['message']); ?>
        </div>
    </div>
    <div class="hide">
        <?php echo $view['form']->row($form['isPublished']); ?>
        <?php echo $view['form']->rest($form); ?>
    </div>
</div>
    </div>
</div>
<?php $view['slots']->stop(); ?>
