<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * Class ConfigSubscriber.
 */
class ConfigSubscriber extends CommonSubscriber
{
    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    /**
     * ConfigSubscriber constructor.
     *
     * @param CoreParametersHelper $coreParametersHelper
     */
    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 4],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigBeforeSave', 0],
        ];
    }

    /**
     * @param ConfigBuilderEvent $event
     */
    public function onConfigGenerate(ConfigBuilderEvent $event)
    {
        $event->addForm([
            'bundle'     => 'EmailBundle',
            'formAlias'  => 'emailconfig',
            'formTheme'  => 'MauticEmailBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('MauticEmailBundle'),
        ]);

        $event->addForm([
            'bundle'     => 'EmailBundle',
            'formAlias'  => 'analyticsconfig',
            'formTheme'  => 'MauticEmailBundle:FormTheme\Analytics',
            'parameters' => ['drip_source'                            => null,
                'drip_medium'                                         => null,
                'drip_campaignname'                                   => null,
                'drip_content'                                        => null,
                'list_source'                                         => null,
                'list_medium'                                         => null,
                'list_campaignname'                                   => null,
                'list_content'                                        => null,
                'analytics_status'                                    => false, ],
        ]);
    }

    /**
     * @param ConfigEvent $event
     */
    public function onConfigBeforeSave(ConfigEvent $event)
    {
        $event->unsetIfEmpty(
            [
                'mailer_password',
                'mailer_api_key',
            ]
        );

        $data = $event->getConfig('emailconfig');

        // Get the original data so that passwords aren't lost
        $monitoredEmail = $this->coreParametersHelper->getParameter('monitored_email');
        if (isset($data['monitored_email'])) {
            foreach ($data['monitored_email'] as $key => $monitor) {
                if (empty($monitor['password']) && !empty($monitoredEmail[$key]['password'])) {
                    $data['monitored_email'][$key]['password'] = $monitoredEmail[$key]['password'];
                }

                if ($key != 'general') {
                    if (empty($monitor['host']) || empty($monitor['address']) || empty($monitor['folder'])) {
                        // Reset to defaults
                        $data['monitored_email'][$key]['override_settings'] = 0;
                        $data['monitored_email'][$key]['address']           = null;
                        $data['monitored_email'][$key]['host']              = null;
                        $data['monitored_email'][$key]['user']              = null;
                        $data['monitored_email'][$key]['password']          = null;
                        $data['monitored_email'][$key]['encryption']        = '/ssl';
                        $data['monitored_email'][$key]['port']              = '993';
                    }
                }
            }
        }

        $event->setConfig($data, 'emailconfig');
    }
}
