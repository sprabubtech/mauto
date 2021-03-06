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
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;

/**
 * Class FormSubscriber.
 */
class FormSubscriber extends CommonSubscriber
{
    /**
     * @var EmailModel
     */
    protected $emailModel;

    /**
     * @var LeadModel
     */
    public $leadModel;

    /**
     * FormSubscriber constructor.
     *
     * @param EmailModel $emailModel
     * @param LeadModel  $leadModel
     */
    public function __construct(EmailModel $emailModel, LeadModel $leadModel)
    {
        $this->emailModel = $emailModel;
        $this->leadModel  = $leadModel;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD                    => ['onFormBuilder', 0],
            LeadEvents::FORM_SUBMIT_REMOVE_DO_NO_CONTACT => ['removeFromDoNotContact', 0],
        ];
    }

    /**
     * Add a lead generation action to available form submit actions.
     *
     * @param FormBuilderEvent $event
     */
    public function onFormBuilder(FormBuilderEvent $event)
    {
        //add lead generation submit action
        $action = [
            'group'       => 'le.lead.lead.submitaction',
            'label'       => 'le.lead.lead.submitaction.changepoints',
            'description' => 'le.lead.lead.submitaction.changepoints_descr',
            'formType'    => 'lead_submitaction_pointschange',
            'formTheme'   => 'MauticLeadBundle:FormTheme\\FormActionChangePoints',
            'callback'    => '\Mautic\LeadBundle\Helper\FormEventHelper::changePoints',
        ];
        $event->addSubmitAction('lead.pointschange', $action);

        //add to lead list
        $action = [
            'group'             => 'le.lead.lead.submitaction',
            'label'             => 'le.lead.lead.events.changelist',
            'description'       => 'le.lead.lead.events.changelist_descr',
            'formType'          => 'leadlist_action',
            'callback'          => '\Mautic\LeadBundle\Helper\FormEventHelper::changeLists',
            'allowCampaignForm' => true,
        ];
        if ($this->factory->getUser()->isAdmin()) {
            $event->addSubmitAction('lead.changelist', $action);
        }

        //add to lead listoptin
        $action = [
            'group'             => 'le.lead.lead.submitaction',
            'label'             => 'le.lead.list.optin.events.form.changelist',
            'description'       => 'le.lead.list.optin.events.changelist_descr',
            'formType'          => 'leadlistoptin_action',
            'formTypeOptions'   => ['isForm' => true],
            'formTheme'         => 'MauticLeadBundle:FormTheme\\FormActionChangeLists',
            'callback'          => '\Mautic\LeadBundle\Helper\FormEventHelper::changeListOptins',
            'allowCampaignForm' => true,
        ];
        $event->addSubmitAction('lead.changelistoptin', $action);

        // modify tags
        $action = [
            'group'             => 'le.lead.lead.submitaction',
            'label'             => 'le.lead.lead.events.changetags',
            'description'       => 'le.lead.lead.events.changetags_descr',
            'formType'          => 'modify_lead_tags',
            'callback'          => '\Mautic\LeadBundle\Helper\EventHelper::updateTags',
            'allowCampaignForm' => true,
        ];
        $event->addSubmitAction('lead.changetags', $action);

        // add UTM tags
        $action = [
            'group'       => 'le.lead.lead.submitaction',
            'label'       => 'le.lead.lead.events.addutmtags',
            'description' => 'le.lead.lead.events.addutmtags_descr',
            'formType'    => 'lead_action_addutmtags',
            'formTheme'   => 'MauticLeadBundle:FormTheme\\ActionAddUtmTags',
            'callback'    => '\Mautic\LeadBundle\Helper\EventHelper::addUtmTags',
        ];
        $event->addSubmitAction('lead.addutmtags', $action);

        // add Do Not Contact
        $action = [
            'group'             => 'le.lead.lead.submitaction',
            'label'             => 'le.lead.lead.events.removedonotcontact',
            'description'       => 'le.lead.lead.events.removedonotcontact_descr',
            'formType'          => 'lead_action_removedonotcontact',
            'formTheme'         => 'MauticLeadBundle:FormTheme\\ActionRemoveDoNotContact',
            'eventName'         => LeadEvents::FORM_SUBMIT_REMOVE_DO_NO_CONTACT,
            'allowCampaignForm' => true,
        ];
        $event->addSubmitAction('lead.removeronotcontact', $action);
        if ($this->security->isAdmin()) {
            // score contact's companies
            $action = [
                'group'       => 'le.lead.lead.submitaction',
                'label'       => 'le.lead.lead.events.changecompanyscore',
                'description' => 'le.lead.lead.events.changecompanyscore_descr',
                'formType'    => 'scorecontactscompanies_action',
                'callback'    => '\Mautic\LeadBundle\Helper\FormEventHelper::scoreContactsCompanies',
            ];
            $event->addSubmitAction('lead.scorecontactscompanies', $action);
        }
    }

    /**
     * Trigger event for when a form is submitted.
     *
     * @param SubmissionEvent $event
     */
    public function removeFromDoNotContact(SubmissionEvent $event)
    {
        $form = $event->getResults();
        $lead = $event->getLead();
        if (isset($form['email']) && !empty($form['email'])) {
            $stat = $this->emailModel->getStatRepository()->findOneBy(
                [
                    'emailAddress' => $form['email'],
                ],
                ['dateSent' => 'DESC']
            );
            if (!empty($stat)) {
                $this->emailModel->removeDoNotContact($stat);
            }
        }
        if (!empty($lead) && $lead != null) {
            foreach ($lead->getDoNotContact() as $dnc) {
                $lead->removeDoNotContactEntry($dnc);

                $this->leadModel->saveEntity($lead);
            }
        }

        return true;
    }
}
