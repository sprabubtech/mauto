<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<?php $isAdmin=$view['security']->isAdmin(); ?>
<div class="row">
    <div class="col-md-6">
        <?php echo $view['form']->row($form['user_id']); ?>
    </div>
    <div class="col-md-6">
        <?php echo $view['form']->row($form['to_owner']); ?>
    </div>

</div>
    <?php echo $view['form']->row($form['to']); ?>
    <div class="<?php echo $isAdmin ? '' : 'hide' ?>">
        <?php echo $view['form']->row($form['cc']); ?>
        <?php echo $view['form']->row($form['bcc']); ?>
    </div>
    <?php echo $view['form']->rest($form); ?>