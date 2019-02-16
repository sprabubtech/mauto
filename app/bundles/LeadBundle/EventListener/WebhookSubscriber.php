<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Event\ChannelSubscriptionChange;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\PointsChangeEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\WebhookBundle\Event\WebhookBuilderEvent;
use Mautic\WebhookBundle\EventListener\WebhookModelTrait;
use Mautic\WebhookBundle\WebhookEvents;

/**
 * Class WebhookSubscriber.
 */
class WebhookSubscriber extends CommonSubscriber
{
    use WebhookModelTrait;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            WebhookEvents::WEBHOOK_ON_BUILD          => ['onWebhookBuild', 10],
            LeadEvents::LEAD_POST_SAVE               => ['onLeadNewUpdate', 0],
            LeadEvents::LEAD_POINTS_CHANGE           => ['onLeadPointChange', 0],
            LeadEvents::LEAD_POST_DELETE             => ['onLeadDelete', 0],
            LeadEvents::CHANNEL_SUBSCRIPTION_CHANGED => ['onChannelSubscriptionChange', 0],
            LeadEvents::MODIFY_TAG_EVENT             => ['onLeadTagModified', 0],
        ];
    }

    /**
     * Add event triggers and actions.
     *
     * @param WebhookBuilderEvent $event
     */
    public function onWebhookBuild(WebhookBuilderEvent $event)
    {
        // add checkbox to the webhook form for new leads
        $event->addEvent(
            LeadEvents::LEAD_POST_SAVE.'_new',
            [
                'label'       => 'le.lead.webhook.event.lead.new',
                'description' => 'le.lead.webhook.event.lead.new_desc',
                ]
        );

        // checkbox for lead updates
        $event->addEvent(
            LeadEvents::LEAD_POST_SAVE.'_update',
            [
                'label'       => 'le.lead.webhook.event.lead.update',
                'description' => 'le.lead.webhook.event.lead.update_desc',
            ]
        );

        // lead deleted checkbox label & desc
        $event->addEvent(
            LeadEvents::LEAD_POST_DELETE,
            [
                'label'       => 'le.lead.webhook.event.lead.deleted',
                'description' => 'le.lead.webhook.event.lead.deleted_desc',
            ]
        );
        // add a checkbox for points
        $event->addEvent(
            LeadEvents::LEAD_POINTS_CHANGE,
            [
                'label'       => 'le.lead.webhook.event.lead.points',
                'description' => 'le.lead.webhook.event.lead.points_desc',
            ]
        );

        // add a checkbox for lead tag modified
        $event->addEvent(
            LeadEvents::MODIFY_TAG_EVENT,
            [
                'label'       => 'le.lead.webhook.event.lead.tag.modified',
                'description' => 'le.lead.webhook.event.lead.tag.modified_desc',
            ]
        );

        if ($this->security->isAdmin()) {
            // add a checkbox for do not contact changes
            $event->addEvent(
                LeadEvents::CHANNEL_SUBSCRIPTION_CHANGED,
                [
                    'label'       => 'le.lead.webhook.event.lead.dnc',
                    'description' => 'le.lead.webhook.event.lead.dnc_desc',
                ]
            );
        }
    }

    /**
     * @param LeadEvent $event
     */
    public function onLeadNewUpdate(LeadEvent $event)
    {
        $lead = $event->getLead();
        if ($lead->isAnonymous()) {
            // Ignore this contact
            return;
        }

        $changes = $lead->getChanges(true);

        $this->webhookModel->queueWebhooksByType(
        // Consider this a new contact if it was just identified, otherwise consider it updated
            !empty($changes['dateIdentified']) ? LeadEvents::LEAD_POST_SAVE.'_new' : LeadEvents::LEAD_POST_SAVE.'_update',
            [
                'lead'    => $event->getLead(),
                //'contact' => $event->getLead(),
            ],
            [
                'leadDetails',
                'userList',
                'publishDetails',
                'ipAddress',
                'tagList',
            ]
        );
    }

    /**
     * @param PointsChangeEvent $event
     */
    public function onLeadPointChange(PointsChangeEvent $event)
    {
        $lead      = $event->getLead();
        $newpoints = $event->getNewPoints();
        $lead->setPoints($newpoints);

        $this->webhookModel->queueWebhooksByType(
            LeadEvents::LEAD_POINTS_CHANGE,
            [
                'lead'    => $event->getLead(),
                //'contact' => $event->getLead(),
                'points'  => [
                    'old_points' => $event->getOldPoints(),
                    'new_points' => $event->getNewPoints(),
                ],
            ],
            [
                'leadDetails',
                'userList',
                'publishDetails',
                'ipAddress',
                'tagList',
            ]
        );
    }

    /**
     * @param LeadEvent $event
     */
    public function onLeadTagModified(LeadEvent $event)
    {
        $this->webhookModel->queueWebhooksByType(
            LeadEvents::MODIFY_TAG_EVENT,
            [
                'lead'     => $event->getLead(),
                //'contact'  => $event->getLead(),
                'tags'     => $event->getLead()->getTags(),
            ],
            [
                'leadDetails',
                'userList',
                'publishDetails',
                'ipAddress',
                'tagList',
            ]
        );
    }

    /**
     * @param LeadEvent $event
     */
    public function onLeadDelete(LeadEvent $event)
    {
        $lead = $event->getLead();
        $this->webhookModel->queueWebhooksByType(
            LeadEvents::LEAD_POST_DELETE,
            [
                'id'      => $lead->deletedId,
                'lead'    => $lead,
                //'contact' => $lead,
            ],
            [
                'leadDetails',
                'userList',
                'publishDetails',
                'ipAddress',
                'tagList',
            ]
        );
    }

    /**
     * @param ChannelSubscriptionChange $event
     */
    public function onChannelSubscriptionChange(ChannelSubscriptionChange $event)
    {
        $this->webhookModel->queueWebhooksByType(
            LeadEvents::CHANNEL_SUBSCRIPTION_CHANGED,
            [
                'lead'       => $event->getLead(),
                'channel'    => $event->getChannel(),
                'old_status' => $event->getOldStatusVerb(),
                'new_status' => $event->getNewStatusVerb(),
            ],
            [
                'leadDetails',
                'userList',
                'publishDetails',
                'ipAddress',
                'tagList',
            ]
        );
    }
}
