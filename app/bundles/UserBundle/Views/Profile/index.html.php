<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

/** @var \Mautic\UserBundle\Entity\User $me */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('leContent', 'user');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.user.account.settings'));
$isAdmin    =$view['security']->isAdmin();
$img        = $view['lead_avatar']->getUserAvatar($me);

?>
<?php echo $view['form']->start($userForm); ?>
<!-- start: box layout -->
<div class="box-layout">
           <!-- step container -->

    <!--/ step container -->

    <!-- container -->
    <div class="col-md-12 bg-auto height-auto ">
        <div class="panel-body">
            <div class="panel panel-default form-group mb-0" style="padding-bottom:120px;">
                <div class="panel-body">
            <div class="tab-pane fade in active bdr-rds-0 bdr-w-0" id="profile">
                <?php echo $view['form']->start($userForm); ?>
                <div class="pa-md bg-auto bg-light-xs bdr-b hide">
                    <h4 class="fw-sb"><?php echo $view['translator']->trans('mautic.user.account.header.details'); ?></h4>
                </div>
                <div class="pa-md">
                    <div class="col-md-6">
                        <?php
                        echo ($permissions['editName']) ? $view['form']->row($userForm['firstName']) : $view['form']->row($userForm['firstName_unbound']);
                        echo $view['form']->row($userForm['mobile']);
                        echo ($permissions['editEmail']) ? $view['form']->row($userForm['email'], ['attr' => ['tabindex' => '-1', 'style' => 'pointer-events: none;background-color: #ebedf0;opacity: 1;']]) : $view['form']->row($userForm['email_unbound']);
                        echo ($permissions['editUsername']) ? $view['form']->row($userForm['username']) : $view['form']->row($userForm['username_unbound']);

                        ?>
                        <div <?php echo ($isAdmin) ? '' : 'class="hide"' ?>> <?php  echo ($permissions['editPosition']) ? $view['form']->row($userForm['position']) : $view['form']->row($userForm['position_unbound']); ?></div>

                    </div>
                    <div class="col-md-6">
                        <?php
                        echo ($permissions['editName']) ? $view['form']->row($userForm['lastName']) : $view['form']->row($userForm['lastName_unbound']);
                        echo $view['form']->row($userForm['timezone']);
                        echo $view['form']->row($userForm['plainPassword']['password']);
                        echo $view['form']->row($userForm['plainPassword']['confirm']);

                        ?>
                        <div <?php echo ($isAdmin) ? '' : 'class="hide"' ?>> <?php echo $view['form']->rowIfExists($userForm, 'signature'); echo $view['form']->row($userForm['locale']); ?></div>
                </div>
                </div>
            </div>

        </div>
            </div>
        </div>
        <?php if ($permissions['apiAccess']): ?>
            <div class="tab-pane fade bdr-rds-0 bdr-w-0" id="clients">
                <div class="pa-md bg-auto bg-light-xs bdr-b">
                    <h4 class="fw-sb"><?php echo $view['translator']->trans('mautic.user.account.header.authorizedclients'); ?></h4>
                </div>
                <div class="pa-md">
                    <?php echo $authorizedClients; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-3 bg-white hide height-auto ">
        <div class="pr-lg pl-lg pt-md pb-md">
            <div class="panel panel-default form-group mb-0">
                <div class="panel-body" style="min-height: 392px;">
                    <center>
            <div class="media">

                <div>
                    <img class="img-circle media-object" style="height:150px;width: 150px" src="<?php echo $img; ?>" alt="" width="200px" height="180px">
                </div>

            </div>
            <div class="media-body" style="margin-top: 10px;">
                <h4><?php echo $me->getName(); ?></h4>
                <h5><?php echo $me->getPosition(); ?></h5>
            </div>
                    </center>
                    <div class="panel panel-default form-group mb-0" style="margin-top: 35px;">
                        <div class="panel-body" >
            <div class="row mt-xs">
                <div class="col-sm-12">
                    <?php echo $view['form']->label($userForm['preferred_profile_image']); ?>
                    <?php echo $view['form']->widget($userForm['preferred_profile_image']); ?>
                </div>
                <div class="col-sm-12<?php if ($view['form']->containsErrors($userForm['custom_avatar'])) {
                            echo ' has-error';
                        } ?>"
                        id="customAvatarContainer"
                        style="<?php if ($userForm['preferred_profile_image']->vars['data'] != 'custom') {
                            echo 'display: none;';
                        } ?>">
                    <?php echo $view['form']->widget($userForm['custom_avatar']); ?>
                    <?php echo $view['form']->errors($userForm['custom_avatar']); ?>
                </div>
            </div>
                        </div>
                    </div>
        </div>
            </div>
        </div>
    </div>

    <!--/ end: container -->
    <?php echo $view['form']->end($userForm); ?>
</div>

<!--/ end: box layout -->
