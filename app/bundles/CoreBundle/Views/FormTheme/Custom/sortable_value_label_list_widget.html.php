<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$isAdmin      =$view['security']->isAdmin();
?>

<div class="input-group sortable-no-reorder">
    <?php if (!empty($preaddon)): ?>
    <span class="input-group-addon preaddon" <?php foreach ($preaddonAttr as $k => $v) {
    printf('%s="%s" ', $view->escape($k), $view->escape($v));
}?>>
    <i class="<?php echo $preaddon; ?>"></i>
    </span>
    <?php endif; ?>
    <div>
        <div class="row">
            <div class="col-xs-6 mr-0 pr-0 bdr-r-wdh-0 <?php echo $isAdmin ? '' : 'hide'?>" id="remove-GDPR-form">
            <?php echo $view['form']->widget($form['label'], ['attr' => ['class' => 'form-control sortable-label le-input', 'placeholder' => $form['label']->vars['label']]]); ?>
            </div>
            <div class="<?php echo $isAdmin ? 'col-xs-6' : 'col-xs-12'?> ml-0 pl-0">
            <?php echo $view['form']->widget($form['value'], ['attr' => ['class' => 'form-control sortable-value le-input', 'placeholder' => $form['value']->vars['label']]]); ?>
            </div>
        </div>
    </div>
    <?php if (!empty($postaddon)): ?>
    <span class="input-group-addon postaddon" <?php foreach ($postaddonAttr as $k => $v) {
    printf('%s="%s" ', $view->escape($k), $view->escape($v));
}?>>
        <i class="<?php echo $postaddon; ?>"></i>
    </span>
    <?php endif; ?>
</div>
