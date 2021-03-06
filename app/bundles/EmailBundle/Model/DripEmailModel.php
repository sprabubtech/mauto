<?php

/*
 * @copyright   2018 LeadsEngage Contributors. All rights reserved
 * @author      LeadsEngage
 *
 * @link        https://leadsengage.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Model;

use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\LicenseInfoHelper;
use Mautic\CoreBundle\Helper\ProgressBarHelper;
use Mautic\CoreBundle\Helper\ThemeHelper;
use Mautic\CoreBundle\Model\BuilderModelTrait;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Model\TranslationModelTrait;
use Mautic\CoreBundle\Model\VariantModelTrait;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\DripEmail;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Lead as DripLead;
use Mautic\EmailBundle\Entity\LeadEventLog as DripLeadEvent;
use Mautic\EmailBundle\Event\DripEmailEvent;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\MonitoredEmail\Mailbox;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Class DripEmailModel
 * {@inheritdoc}
 */
class DripEmailModel extends FormModel
{
    use VariantModelTrait;
    use TranslationModelTrait;
    use BuilderModelTrait;

    /**
     * @var IpLookupHelper
     */
    protected $ipLookupHelper;

    /**
     * @var ThemeHelper
     */
    protected $themeHelper;

    /**
     * @var Mailbox
     */
    protected $mailboxHelper;

    /**
     * @var MailHelper
     */
    public $mailHelper;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var CompanyModel
     */
    protected $companyModel;

    /**
     * @var TrackableModel
     */
    protected $pageTrackableModel;

    /**
     * @var UserModel
     */
    protected $userModel;

    /**
     * @var MessageQueueModel
     */
    protected $messageQueueModel;

    /**
     * @var bool
     */
    protected $updatingTranslationChildren = false;

    /**
     * @var array
     */
    protected $emailSettings = [];

    /**
     * @var Send
     */
    protected $sendModel;

    /**
     * @var LicenseInfoHelper
     */
    protected $licenseInfoHelper;

    /**
     * @var EmailModel
     */
    protected $emailModel;

    /**
     * EmailModel constructor.
     *
     * @param IpLookupHelper     $ipLookupHelper
     * @param ThemeHelper        $themeHelper
     * @param Mailbox            $mailboxHelper
     * @param MailHelper         $mailHelper
     * @param LeadModel          $leadModel
     * @param CompanyModel       $companyModel
     * @param TrackableModel     $pageTrackableModel
     * @param UserModel          $userModel
     * @param MessageQueueModel  $messageQueueModel
     * @param SendEmailToContact $sendModel
     * @param LicenseInfoHelper  $licenseInfoHelper
     * @param EmailModel         $emailModel
     */
    public function __construct(
        IpLookupHelper $ipLookupHelper,
        ThemeHelper $themeHelper,
        Mailbox $mailboxHelper,
        MailHelper $mailHelper,
        LeadModel $leadModel,
        CompanyModel $companyModel,
        TrackableModel $pageTrackableModel,
        UserModel $userModel,
        MessageQueueModel $messageQueueModel,
        SendEmailToContact $sendModel,
        LicenseInfoHelper  $licenseInfoHelper,
        EmailModel  $emailModel
    ) {
        $this->ipLookupHelper     = $ipLookupHelper;
        $this->themeHelper        = $themeHelper;
        $this->mailboxHelper      = $mailboxHelper;
        $this->mailHelper         = $mailHelper;
        $this->leadModel          = $leadModel;
        $this->companyModel       = $companyModel;
        $this->pageTrackableModel = $pageTrackableModel;
        $this->userModel          = $userModel;
        $this->messageQueueModel  = $messageQueueModel;
        $this->sendModel          = $sendModel;
        $this->licenseInfoHelper  = $licenseInfoHelper;
        $this->emailModel         = $emailModel;
    }

    public function getEmailModel()
    {
        return $this->emailModel;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Mautic\EmailBundle\Entity\DripEmailRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:DripEmail');
    }

    /**
     * {@inheritdoc}
     *
     * @return \Mautic\EmailBundle\Entity\EmailRepository
     */
    public function getEmailRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:Email');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\StatRepository
     */
    public function getStatRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:Stat');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\CopyRepository
     */
    public function getCopyRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:Copy');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\StatDeviceRepository
     */
    public function getStatDeviceRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:StatDevice');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\AwsConfig
     */
    public function getAwsConfigRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:AwsConfig');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\AwsVerifiedEmails
     */
    public function getAwsVerifiedEmailsRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:AwsVerifiedEmails');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\LeadRepository
     */
    public function getCampaignLeadRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:Lead');
    }

    /**
     * @return \Mautic\EmailBundle\Entity\LeadEventLogRepository
     */
    public function getCampaignLeadEventRepository()
    {
        return $this->em->getRepository('MauticEmailBundle:LeadEventLog');
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionBase()
    {
        return 'email:emails';
    }

    /**
     * {@inheritdoc}
     *
     * @param Email $entity
     * @param       $unlock
     *
     * @return mixed
     */
    public function saveEntity($entity, $unlock = true)
    {
        $isNew = ($entity->getId()) ? false : true;
        if ($isNew) {
            $entity->setIsPublished(true);
        }
        parent::saveEntity($entity, $unlock);

        $this->dispatchEvent('post_save', $entity, $isNew);
    }

    /**
     * @param Email $entity
     */
    public function deleteEntity($entity)
    {
        parent::deleteEntity($entity);

        $this->dispatchEvent('post_delete', $entity, false);
    }

    /**
     * {@inheritdoc}
     *
     * @param       $entity
     * @param       $formFactory
     * @param null  $action
     * @param array $options
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof DripEmail) {
            throw new MethodNotAllowedHttpException(['DripEmail']);
        }
        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create('dripemailform', $entity, $options);
    }

    /**
     * Get a specific entity or generate a new one if id is empty.
     *
     * @param $id
     *
     * @return null|DripEmail
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            $entity = new DripEmail();
        } else {
            $entity = parent::getEntity($id);
            if ($entity !== null) {
                $entity->setSessionId($entity->getId());
            }
        }

        return $entity;
    }

    /**
     * Return a list of entities.
     *
     * @param array $args [start, limit, filter, orderBy, orderByDir]
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator|array
     */
    public function getEntities(array $args = [], $includeStat=false)
    {
        $entities = parent::getEntities($args);
        if ($includeStat) {
            foreach ($entities as $entity) {
                $cacheHelper = $this->factory->getHelper('cache_storage');

                $pending               = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_pending'));
                $scheduled             = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_scheduled'));
                $sent                  = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_sent'));
                $open                  = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_open'));
                $click                 = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_click'));
                $unsubscribe           = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_unsubscribe'));
                $openPercentage        = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_open_percentage'));
                $clickPercentage       = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_click_percentage'));
                $unsubscribePercentage = $cacheHelper->get(sprintf('%s|%s|%s', 'dripemail', $entity->getId(), 'drip_unsubscribe_percentage'));

                if ($pending !== false) {
                    $entity->setPendingCount($pending);
                }

                if ($scheduled !== false) {
                    $entity->setScheduledCount($scheduled);
                }

                if ($sent !== false) {
                    $entity->setSentCount($sent);
                }

                if ($open !== false) {
                    $entity->setOpenCount($open);
                }

                if ($click !== false) {
                    $entity->setClickCount($click);
                }

                if ($unsubscribe !== false) {
                    $entity->setUnsubscribeCount($unsubscribe);
                }

                if ($openPercentage !== false) {
                    $entity->setOpenPercentage($openPercentage);
                }

                if ($clickPercentage !== false) {
                    $entity->setClickPercentage($clickPercentage);
                }

                if ($unsubscribePercentage !== false) {
                    $entity->setUnsubscribePercentage($unsubscribePercentage);
                }
            }
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     *
     * @param $action
     * @param $event
     * @param $entity
     * @param $isNew
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof DripEmail) {
            throw new MethodNotAllowedHttpException(['DripEmail']);
        }

        switch ($action) {
            case 'pre_save':
                $name = EmailEvents::EMAIL_PRE_SAVE;
                break;
            case 'post_save':
                $name = EmailEvents::DRIPEMAIL_POST_SAVE;
                break;
            case 'pre_delete':
                $name = EmailEvents::EMAIL_PRE_DELETE;
                break;
            case 'post_delete':
                $name = EmailEvents::DRIPEMAIL_POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            $event = new DripEmailEvent($entity, $isNew);
            $event->setEntityManager($this->em);

            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return null;
        }
    }

    /**
     * Get a list of emails in a date range.
     *
     * @param int       $limit
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param array     $filters
     * @param array     $options
     *
     * @return array
     */
    public function getEmailList($limit = 10, \DateTime $dateFrom = null, \DateTime $dateTo = null, $filters = [], $options = [])
    {
        $q = $this->em->getConnection()->createQueryBuilder();
        $q->select('t.id, t.name, t.date_added, t.date_modified')
            ->from(MAUTIC_TABLE_PREFIX.'emails', 't')
            ->setMaxResults($limit);

        if (empty($options['canViewOthers']) || $options['canViewOthers'] == '') {
            $q->andWhere('t.created_by = :userId')
                ->setParameter('userId', $this->userHelper->getUser()->getId());
        }

        $chartQuery = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);
        $chartQuery->applyFilters($q, $filters);
        $chartQuery->applyDateFilters($q, 'date_added');

        $results = $q->execute()->fetchAll();

        return $results;
    }

    public function addLead($campaign, $lead, $checkedAlready=false)
    {
        $added = false;
        if ($checkedAlready || $this->checkLeadLinked($lead, $campaign)) {
            $dripCampaign = new DripLead();
            $dripCampaign->setCampaign($campaign);
            $dripCampaign->setLead($lead);
            $dripCampaign->setDateAdded(new \DateTime());
            $dripCampaign->setManuallyAdded(true);

            $this->saveCampaignLead($dripCampaign);

            if ($this->dispatcher->hasListeners(LeadEvents::LEAD_DRIP_CAMPAIGN_ADD)) {
                $event = new LeadEvent($lead, true);
                $event->setDrip($campaign);
                $this->dispatcher->dispatch(LeadEvents::LEAD_DRIP_CAMPAIGN_ADD, $event);
                unset($event);
            }
            $added = true;
        }

        return $added;
    }

    public function addLeadToDrip($campaign, $lead)
    {
        $added = $this->addLead($campaign, $lead);
        $items = $this->emailModel->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'e.dripEmail',
                            'expr'   => 'eq',
                            'value'  => $campaign,
                        ],
                    ],
                ],
                'orderBy'          => 'e.dripEmailOrder',
                'orderByDir'       => 'asc',
                'ignore_paginator' => true,
            ]
        );
        if ($added) {
            $this->scheduleEmail($items, $campaign, $lead);
        }
    }

    public function removeLead($dripId, $leadId)
    {
        $eventLogRepo=$this->getCampaignLeadEventRepository();
        $eventLogRepo->removeScheduledEvents($dripId, $leadId);
        $eventLogRepo->removeScheduledDripLead($dripId, $leadId);
    }

    public function checkLeadLinked($lead, $dripemail)
    {
        $leadLog  = $this->factory->get('mautic.email.repository.lead');
        $items    = $leadLog->checkisLeadLinked($lead, $dripemail);
        if (empty($items)) {
            unset($items);

            return true;
        } else {
            unset($items);

            return false;
        }
    }

    public function saveCampaignLead(DripLead $campaignLead)
    {
        try {
            $this->getCampaignLeadRepository()->saveEntity($campaignLead);

            return true;
        } catch (\Exception $exception) {
            $this->logger->log('error', $exception->getMessage());

            return false;
        }
    }

    public function saveCampaignLeadEvent(DripLeadEvent $campaignLead)
    {
        try {
            $leadEventLog  = $this->factory->get('mautic.email.repository.leadEventLog');
            $leadEventLog->saveEntity($campaignLead);

            return true;
        } catch (\Exception $exception) {
            $this->logger->log('error', $exception->getMessage());

            return false;
        }
    }

    public function checkLeadCompleted($lead, $dripemail, $email)
    {
        $leadEventLog  = $this->factory->get('mautic.email.repository.leadEventLog');
        $items         = $leadEventLog->checkisLeadCompleted($lead, $dripemail, $email);
        if (empty($items)) {
            return true;
        } else {
            return false;
        }
    }

    public function scheduleEmail($entities, $dripemail, $lead)
    {
        $isFirstmailToday      = false;
        $timezone              = $this->coreParametersHelper->getParameter('default_timezone');
        date_default_timezone_set('UTC');
        $previousDate     = date('Y-m-d H:i:s');
        $isLastEmail      = false;
        $emailCount       = 0;
        $dateHelper       = $this->factory->get('mautic.helper.template.date');
        foreach ($entities as $entity) {
            $emailCount = $emailCount + 1;
            if (!$entity->getIsPublished()) { //!$this->checkLeadCompleted($lead, $dripemail, $entity) ||
                continue;
            }
            if ($entity->getDripEmailOrder() == 1) {
                $dayscount        = 0;
                $configdays       = $dripemail->getDaysEmailSend();
                $dripScheduleTime = $dripemail->getScheduleDate();
                if ($dripScheduleTime != '') {
                    $date             = date('Y-m-d').' '.$dripScheduleTime;
                    $newTime          = $dateHelper->toTime($date, $timezone);
                    $dripScheduleTime = explode(' ', $newTime)[0];
                }
                if ($dripScheduleTime == '' || strtotime($dripScheduleTime) < strtotime(date('H:i'))) {
                    $dripScheduleTime = date('H:i');
                }
                if (!empty($configdays)) {
                    for ($i = 0; $i < 7; ++$i) {
                        $currentDay = date('D', strtotime('+'.$i.' day'));
                        if (!in_array($currentDay, $configdays)) {
                            continue;
                        } else {
                            $isFirstmailToday = true;
                            $dayscount        = $i;
                            break;
                        }
                    }
                } else {
                    $isFirstmailToday = true;
                }
                $scheduleTime = date('Y-m-d H:i:s', strtotime('+'.$entity->getScheduleTime().' + '.$dayscount.' days', strtotime($dripScheduleTime)));
            } else {
                $scheduleTime = date('Y-m-d H:i:s', strtotime($previousDate.'+'.$entity->getScheduleTime()));
            }
            if (!$isFirstmailToday) {
                continue;
            }
            if ($emailCount == count($entities)) {
                $isLastEmail = true;
            }
            $previousDate = $scheduleTime;
            //dump($isFirstmailToday);
            //dump($scheduleTime);
            $dripevent = new DripLeadEvent();
            $dripevent->setLead($lead);
            $dripevent->setCampaign($dripemail);
            $dripevent->setEmail($entity);
            $dripevent->setTriggerDate($scheduleTime);
            $dripevent->setDateTriggered(date('Y-m-d H:i:s'));
            $dripevent->setIsScheduled(true);
            $dripevent->setRotation($isLastEmail);
            $this->saveCampaignLeadEvent($dripevent);
        }
    }

    public function sendDripEmailtoLead($email, $lead)
    {
        $options   = [
            'source'         => [],
            'email_attempts' => 3,
            'email_priority' => 2,
            'email_type'     => 'transactional',
            'return_errors'  => true,
            'dnc_as_error'   => true,
            'source'         => ['email', $email->getId()],
            'allowResends'   => false,
            'customHeaders'  => [],
        ];

        //getLead
        $leadModel       = $this->factory->get('mautic.lead.model.lead');
        $leadCredentials = $leadModel->getRepository()->getLead($lead->getId());
        $emailSent       = $this->emailModel->sendEmail($email, $leadCredentials, $options);

        if (is_array($emailSent)) {
            $errors = implode('<br />', $emailSent);

            // Add to the metadata of the failed event
            $emailSent = [
                'result' => false,
                'errors' => $errors,
            ];
        } elseif (true !== $emailSent) {
            $emailSent = [
                'result' => false,
                'errors' => $emailSent,
            ];
        } else {
            $emailSent = [
                'result' => true,
                'errors' => '',
            ];
        }

        return $emailSent;
    }

    public function getDripEmailBlocks()
    {
        $sentCount =  [$this->translator->trans('le.form.display.color.blocks.blue'), 'mdi mdi-email-outline', $this->translator->trans('le.email.sent.last30days.sent'),
            $this->getRepository()->getLast30DaysDripSentCounts($viewOthers = $this->factory->get('mautic.security')->isGranted('dripemail:emails:viewother')),
        ];
        $openCount = [$this->translator->trans('le.form.display.color.blocks.green'), 'mdi mdi-email-open-outline', $this->translator->trans('le.email.sent.last30days.opens'),
            $this->getRepository()->getLast30DaysDripOpensCounts($viewOthers = $this->factory->get('mautic.security')->isGranted('dripemail:emails:viewother')),
        ];
        $clickCount = [$this->translator->trans('le.form.display.color.blocks.orange'), 'mdi mdi-email-open-outline', $this->translator->trans('le.email.sent.last30days.clicks'),
            $this->getRepository()->getLast30DaysDripClickCounts($viewOthers = $this->factory->get('mautic.security')->isGranted('dripemail:emails:viewother')),
        ];
        $unsubacribeCount = [$this->translator->trans('le.form.display.color.blocks.red'), 'mdi mdi-email-variant', $this->translator->trans('le.email.sent.drip.unsubscribe'),
            $this->getRepository()->getDripUnsubscribeCounts($viewOthers = $this->factory->get('mautic.security')->isGranted('dripemail:emails:viewother')),
        ];

        $allBlockDetails[] = $sentCount;
        $allBlockDetails[] = $openCount;
        $allBlockDetails[] = $clickCount;
        $allBlockDetails[] = $unsubacribeCount;

        return $allBlockDetails;
    }

    public function scheduleOneOffEmail($leads, $dripemail = null, $email)
    {
        $previousDate     = date('Y-m-d H:i:s');
        foreach ($leads as $lead) {
            $leadEntity = $this->leadModel->getEntity($lead['id']);
            //file_put_contents("/var/www/log.txt",json_encode($leadEntity)."\n",FILE_APPEND);
            $dripevent = new DripLeadEvent();
            $dripevent->setLead($leadEntity);
            $dripevent->setCampaign($dripemail);
            $dripevent->setEmail($email);
            $dripevent->setTriggerDate($previousDate);
            $dripevent->setDateTriggered(date('Y-m-d H:i:s'));
            $dripevent->setIsScheduled(true);
            $this->saveCampaignLeadEvent($dripevent);
        }
    }

    /**
     * Batch sleep according to settings.
     */
    protected function batchSleep()
    {
        $leadSleepTime = $this->coreParametersHelper->getParameter('batch_lead_sleep_time', false);
        if ($leadSleepTime === false) {
            $leadSleepTime = $this->coreParametersHelper->getParameter('batch_sleep_time', 1);
        }

        if (empty($leadSleepTime)) {
            return;
        }

        if ($leadSleepTime < 1) {
            usleep($leadSleepTime * 1000000);
        } else {
            sleep($leadSleepTime);
        }
    }

    public function rebuildLeadRecipients(DripEmail $entity, $limit = 1000, $maxLeads = false, OutputInterface $output = null)
    {
        $id       = $entity->getId();
        $drip     = ['id' => $id, 'filters' => $entity->getRecipients()];

        // Get a count of leads to add
        $newLeadsCount = $this->getLeadsByDrip(
            $entity,
            true
        );

        // Number of total leads to process
        $leadCount = (int) $newLeadsCount;

        if ($output) {
            $output->writeln($this->translator->trans('le.drip.email.lead.rebuild.to_be_added', ['%leads%' => $leadCount, '%batch%' => $limit]));
        }

        // Handle by batches
        $lastRoundPercentage = $leadsProcessed = 0;

        // Try to save some memory
        gc_enable();
        if ($leadCount) {
            $maxCount = ($maxLeads) ? $maxLeads : $leadCount;

            if ($output) {
                $progress = ProgressBarHelper::init($output, $maxCount);
                $progress->start();
            }
            $newLeadList = $this->getLeadsByDrip(
                $entity,
                false, false, $limit
            );
            $leadCount = count($newLeadList);
            // Add leads
            while ($leadCount > 0) {
                $this->emailModel->beginTransaction();
                try {
                    foreach ($newLeadList as $id => $l) {
                        $lead = $this->leadModel->getEntity($l['id']);
                        if ($this->checkLeadLinked($lead, $entity)) {
                            $this->addLead($entity, $lead, true);
                            $items = $this->emailModel->getEntities(
                                [
                                    'filter' => [
                                        'force' => [
                                            [
                                                'column' => 'e.dripEmail',
                                                'expr'   => 'eq',
                                                'value'  => $entity,
                                            ],
                                        ],
                                    ],
                                    'orderBy'          => 'e.dripEmailOrder',
                                    'orderByDir'       => 'asc',
                                    'ignore_paginator' => true,
                                ]
                            );

                            $this->scheduleEmail($items, $entity, $lead);
                            $processedLeads[] = $l;
                            unset($l);
                            ++$leadsProcessed;
                        }
                        unset($lead);
                    }
                    $this->emailModel->commitTransaction();
                } catch (\Exception $ex) {
                    $output->writeln('Exception occured at batch execution->'.$ex->getMessage());
                    $this->emailModel->rollbackTransaction();
                    throw $ex;
                }
                if ($output && $leadsProcessed < $maxCount) {
                    $progress->setProgress($leadsProcessed);
                }
                unset($newLeadList);
                // Keep CPU down for large lists; sleep per $limit batch
                $this->batchSleep();
                $newLeadList = $this->getLeadsByDrip(
                    $entity,
                    false, false, $limit
                );
                $leadCount = count($newLeadList);
            }
            // Free some memory
            gc_collect_cycles();
            if ($output) {
                $progress->finish();
                $output->writeln('');
            }
        }

        return $leadsProcessed;
    }

    /**
     * @param       $lists
     * @param bool  $idOnly
     * @param array $args
     *
     * @return mixed
     */
    public function getLeadsByDrip($drip, $countOnly = false, $returnQuery = false, $limit=false)
    {
        return $this->getRepository()->getLeadsByDrip($drip, $countOnly, $returnQuery, $limit);
    }

    public function getLeadIdsByDrip($drip)
    {
        return $this->getRepository()->getLeadIdsByDrip($drip);
    }

    public function getCustomEmailStats($drip)
    {
        $emails = $this->emailModel->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'e.dripEmail',
                            'expr'   => 'eq',
                            'value'  => $drip,
                        ],
                    ],
                ],
                'orderBy'          => 'e.dripEmailOrder',
                'orderByDir'       => 'asc',
                'ignore_paginator' => true,
            ]
        );
        $sentcount        = 0;
        $uopencount       = 0;
        $topencount       = 0;
        $nopencount       = 0;
        $clickcount       = 0;
        $unsubscribecount = 0;
        $bouncecount      = 0;
        $spamcount        = 0;
        $failedcount      = 0;
        foreach ($emails as $item) {
            $sentcount += $this->emailModel->getRepository()->getTotalSentCounts($item->getId());
            $uopencount += $this->emailModel->getRepository()->getTotalUniqueOpenCounts($item->getId());
            $topencount += $this->emailModel->getRepository()->getTotalOpenCounts($item->getId());
            $nopencount += $this->emailModel->getRepository()->getTotalNotOpenCounts($item->getId());
            $clickcount += $this->emailModel->getRepository()->getEmailClickCounts($item->getId());
            $unsubscribecount += $this->emailModel->getRepository()->getTotalUnsubscribedCounts($item->getId());
            $bouncecount += $this->emailModel->getRepository()->getTotalBounceCounts($item->getId());
            $spamcount += $this->emailModel->getRepository()->getTotalSpamCounts($item->getId());
            $failedcount += $this->emailModel->getRepository()->getTotalFailedCounts($item->getId());
        }
        $emailStats                = [];
        $emailStats['sent']        = $sentcount;
        $emailStats['uopen']       = $uopencount;
        $emailStats['topen']       = $topencount;
        $emailStats['click']       = $clickcount;
        $emailStats['unsubscribe'] = $unsubscribecount;
        $emailStats['bounce']      = $bouncecount;
        $emailStats['spam']        = $spamcount;
        $emailStats['nopen']       = $nopencount;
        $emailStats['failed']      = $failedcount;

        return $emailStats;
    }

    public function getDripByLead($leadId, $publishedonly = true)
    {
        return $this->getRepository()->getDripByLead($leadId, $publishedonly);
    }

    public function getDripEmailStats($ids = null, $percentageNeeded= true, Request $request = null)
    {
        $cacheHelper = $this->factory->getHelper('cache_storage');

        /** @var DripEmailModel $model */
        $model = $this->factory->getModel('email.dripemail');

        /** @var EmailModel $model */
        $emailmodel = $this->factory->getModel('email');

        /** @var LeadRepository $leadRepo */
        $leadRepo           = $this->factory->get('mautic.email.repository.lead');
        $leadEventLogRepo   = $this->factory->get('mautic.email.repository.LeadEventLog');

        $data        = [];
        foreach ($ids as $id) {
            if ($dripemail = $model->getEntity($id)) {
                $emailEntities = $emailmodel->getEntities(
                    [
                        'filter'           => [
                            'force' => [
                                [
                                    'column' => 'e.dripEmail',
                                    'expr'   => 'eq',
                                    'value'  => $dripemail,
                                ],
                            ],
                        ],
                        'orderBy'          => 'e.dripEmailOrder',
                        'orderByDir'       => 'asc',
                        'ignore_paginator' => true,
                    ]
                );
                $leads = $leadRepo->getEntities(
                    [
                        'filter'           => [
                            'force' => [
                                [
                                    'column' => 'le.campaign',
                                    'expr'   => 'eq',
                                    'value'  => $dripemail,
                                ],
                            ],
                        ],
                        'ignore_paginator' => true,
                    ]
                );
                $activeLeads = [];
                foreach ($leads as $lead) {
                    $eventLogLead = $leadEventLogRepo->getEntities(
                        [
                            'filter'           => [
                                'force' => [
                                    [
                                        'column' => 'dle.campaign',
                                        'expr'   => 'eq',
                                        'value'  => $dripemail,
                                    ],
                                    [
                                        'column' => 'dle.lead',
                                        'expr'   => 'eq',
                                        'value'  => $lead->getLead(),
                                    ],
                                    [
                                        'column' => 'dle.isScheduled',
                                        'expr'   => 'eq',
                                        'value'  => '1',
                                    ],
                                ],
                            ],
                            'ignore_paginator' => true,
                        ]
                    );

                    if (count($eventLogLead)) {
                        $activeLeads[] = $lead;
                    }
                }

                $dripSentCount   = 0;
                $dripReadCount   = 0;
                $dripClickCount  = 0;
                $dripUnsubCount  = 0;
                $dripBounceCount = 0;
                $dripSpamCount   = 0;
                $dripFailedCount = 0;
                foreach ($emailEntities as $email) {
                    $sentCount        = $email->getSentCount(true);
                    $readCount        = $email->getReadCount(true);
                    $clickCount       = $emailmodel->getEmailClickCount($email->getId());
                    $failedcount      = $emailmodel->getEmailFailedCount($email->getId());
                    $unsubCount       = $email->getUnsubscribeCount(true);
                    $bouncecount      = $email->getBounceCount(true);
                    $spamcount        = $email->getSpamCount(true);
                    $dripSentCount += $sentCount;
                    $dripReadCount += $readCount;
                    $dripClickCount += $clickCount;
                    $dripUnsubCount += $unsubCount;
                    $dripBounceCount += $bouncecount;
                    $dripSpamCount += $spamcount;
                    $dripFailedCount += $failedcount;
                }
                $dripclickCountPercentage  = 0;
                $dripreadCountPercentage   = 0;
                $dripunsubsCountPercentage = 0;
                $dripbounceCountPercentage = 0;
                $dripspamCountPercentage   = 0;
                $dripfailedCountPercentage = 0;
                if ($dripClickCount > 0 && $dripSentCount > 0) {
                    $dripclickCountPercentage  = round($dripClickCount / $dripSentCount * 100);
                }
                if ($dripReadCount > 0 && $dripSentCount > 0) {
                    $dripreadCountPercentage   = round($dripReadCount / $dripSentCount * 100);
                }
                if ($dripUnsubCount > 0 && $dripSentCount > 0) {
                    $dripunsubsCountPercentage = round($dripUnsubCount / $dripSentCount * 100);
                }
                if ($dripBounceCount > 0 && $dripSentCount > 0) {
                    $dripbounceCountPercentage = round($dripBounceCount / $dripSentCount * 100);
                }
                if ($dripSpamCount > 0 && $dripSentCount > 0) {
                    $dripspamCountPercentage = round($dripSpamCount / $dripSentCount * 100);
                }
                if ($dripFailedCount > 0 && sizeof($leads) > 0) {
                    $dripfailedCountPercentage = round($dripFailedCount / sizeof($leads) * 100);
                }
                if ($percentageNeeded) {
                    $pending       = $this->getLeadsByDrip($dripemail, true);

                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_pending'), $pending);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_scheduled'), sizeof($activeLeads));
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_sent'), $dripSentCount);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_open'), $dripReadCount);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_click'), $dripClickCount);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_unsubscribe'), $dripUnsubCount);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_open_percentage'), $dripreadCountPercentage);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_click_percentage'), $dripclickCountPercentage);
                    $cacheHelper->set(sprintf('%s|%s|%s', 'dripemail', $dripemail->getId(), 'drip_unsubscribe_percentage'), $dripunsubsCountPercentage);

                    $data[]= [
                        'id'               => $id,
                        'sentcount'        => $this->translator->trans('le.drip.email.stat.sentcount', ['%count%'  =>$dripSentCount]),
                        'readcount'        => $this->translator->trans('le.drip.email.stat.opencount', ['%count%'  =>$dripReadCount, '%percentage%'  => $dripreadCountPercentage]),
                        'clickcount'       => $this->translator->trans('le.drip.email.stat.clickcount', ['%count%' =>$dripClickCount, '%percentage%' => $dripclickCountPercentage]),
                        'unsubscribe'      => $this->translator->trans('le.drip.email.stat.unsubscribe', ['%count%' =>$dripUnsubCount, '%percentage%' => $dripunsubsCountPercentage]),
                        'bouncecount'      => $this->translator->trans('le.drip.email.stat.bounce', ['%count%' =>$dripBounceCount, '%percentage%' => $dripbounceCountPercentage]),
                        'spam'             => $this->translator->trans('le.drip.email.stat.spam', ['%count%' =>$dripSpamCount, '%percentage%' => $dripspamCountPercentage]),
                        'failed'           => $this->translator->trans('le.drip.email.stat.failed', ['%count%' =>$dripFailedCount, '%percentage%' => $dripfailedCountPercentage]),
                        'leadcount'        => $this->translator->trans('le.drip.email.stat.leadcount', ['%count%'  => sizeof($activeLeads)]),
                        'pendingcount'     => $this->translator->trans('le.drip.email.stat.pendingcount', ['%count%'  => $pending]),
                    ];
                } else {
                    $data[]= [
                        'sentcount'   => $dripSentCount,
                        'readcount'   => $dripReadCount,
                        'clickcount'  => $dripClickCount,
                        'unsubscribe' => $dripUnsubCount,
                        'bouncecount' => $dripBounceCount,
                        'spam'        => $dripSpamCount,
                        'failed'      => $dripFailedCount,
                        'leadcount'   => sizeof($activeLeads),
                    ];
                }
            }
        }

        if ($percentageNeeded) {
            // Support for legacy calls
            if ($request->get('id')) {
                $data = $data[0];
            } else {
                $data = [
                    'success' => 1,
                    'stats'   => $data,
                ];
            }
        }

        return $data;
    }
}
