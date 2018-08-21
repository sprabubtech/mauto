<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/html">
<head>
    <meta charset="UTF-8" />
    <title><?php echo $view['slots']->get('pageTitle', 'LeadsEngage'); ?></title>
    <meta name="robots" content="noindex, nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="icon" type="image/x-icon" href="<?php echo $view['assets']->getUrl('media/images/favicon.ico') ?>" />
    <link rel="apple-touch-icon" href="<?php echo $view['assets']->getUrl('media/images/apple-touch-icon.png') ?>" />
    <?php $view['assets']->outputSystemStylesheets(); ?>
    <?php echo $view->render('MauticCoreBundle:Default:script.html.php'); ?>
    <?php $view['assets']->outputHeadDeclarations(); ?>
</head>
<body>
<section id="wrapper" class="le-new-login-register">
    <div class="lg-info-panel" style="background: url(<?php echo $view['assets']->getUrl('media/images/login-register.jpg') ?>) no-repeat center center / cover!important;">
        <div class="inner-panel">
            <div class="lg-content">
                <h2 class="lg-content-header"><?php echo $view['translator']->trans('le.users.content.header'); ?></h2>
                <p class="text-muted le-content-description"> <?php echo $view['translator']->trans('le.users.content.desc'); ?></p>
                <a class="btn btn-rounded le-btn-danger p-l-20 p-r-20" style="color: #fff;" href="https://leadsengage.com/" target="_blank"><?php echo $view['translator']->trans('le.users.content.button'); ?></a>
            </div>
        </div>
    </div>
    <div class="new-login-box">
        <div class="white-box">
            <div>
                <img style="
    width: 190px;
    margin-bottom: 25px;
    margin-top: 40px;
" src="<?php echo $view['assets']->getUrl('media/images/leadsengage_logo-black.png') ?>">
            </div>
            <div id="main-panel-flash-msgs">
                <?php echo $view->render('MauticCoreBundle:Notification:flashes.html.php'); ?>
            </div>
            <?php $view['slots']->output('_content'); ?>
        </div>
    </div>
<?php echo $view['security']->getAuthenticationContent(); ?>
</body>
</section>
</html>
