<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SubscriptionBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StateMachineCommand extends ModeratedCommand
{
    protected function configure()
    {
        $this
            ->setName('le:statemachine:checker')
            ->setAliases(['le:statemachine:checker'])
            ->setDescription('Check the application status and update')
            ->addOption('--domain', '-d', InputOption::VALUE_REQUIRED, 'To load domain specific configuration', '');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $domain    = $input->getOption('domain');
            if (!$this->checkRunStatus($input, $output, $domain)) {
                return 0;
            }
            $container          = $this->getContainer();
            $translator         = $container->get('translator');
            $smHelper           =$container->get('le.helper.statemachine');
            $paymentrepository  =$container->get('le.subscription.repository.payment');
            $lastpayment        = $paymentrepository->getLastPayment();
            $prefix             = 'Trial';
            if ($lastpayment != null) {
                $prefix = 'Customer';
            }
            if (!$smHelper->isStateAlive($prefix.'_Inactive_Archive')) {
                $checkStateFurther=true;
                if ($state=$smHelper->isStateAlive('Trial_Unverified_Email')) {
                    $updatedOn         =$state->getUpdatedOn();
                    $dtHelper          =new DateTimeHelper();
                    $diffdays          =$dtHelper->getDiff($updatedOn, '%R%a', true);
                    if ($diffdays > 3) {
                        $updatedOn=$dtHelper->getLocalDateTime();
                        $updatedOn=$container->get('mautic.helper.template.date')->toDate($updatedOn, 'local');
                        $smHelper->makeStateInActive(['Trial_Unverified_Email']);
                        $smHelper->newStateEntry('Trial_Inactive_Archive', $smHelper->getAlertMessage('le.subscription.isactive.archive.unverified.email.reason', ['%DATE%' => $updatedOn, '%DAYS%' => $diffdays]));
                        $smHelper->addStateWithLead();
                        $checkStateFurther=false;
                    }
                } else {
                    if ($checkStateFurther) {
                        $licenseRemDays = $container->get('mautic.helper.licenseinfo')->getLicenseRemainingDays();
                        if ($lastpayment == null && $licenseRemDays < 0) {
                            if (!$smHelper->isStateAlive('Trial_Inactive_Expired')) {
                                $smHelper->makeStateInActive(['Trial_Active']);
                                $smHelper->newStateEntry('Trial_Inactive_Expired');
                                $smHelper->addStateWithLead();
                            }
                            $checkStateFurther=false;
                        }
                    }
                    if ($checkStateFurther) {
                        //to check Customer_Inactive_Sending_Domain_Issue state
                        if (!$smHelper->isStateAlive($prefix.'_Inactive_Sending_Domain_Issue')) {
                            if ($smHelper->isStateNotAlive($prefix.'_Sending_Domain_Not_Configured')) {
                                $domainStatus=$smHelper->checkSendingDomainStatus();
                                if (!$domainStatus) {
                                    $smHelper->makeStateInActive([$prefix.'_Active']);
                                    $smHelper->newStateEntry($prefix.'_Inactive_Sending_Domain_Issue', '');
                                    $smHelper->sendSendingDomainIssueEmail();
                                    $smHelper->addStateWithLead();
                                    $output->writeln('<info>App enters into Customer_Inactive_Sending_Domain_Issue</info>');
                                } else {
                                    $output->writeln('<info>Active sending domains are presented</info>');
                                }
                            }
                        }
                    }
                    if ($checkStateFurther) {
                        //to check Customer_Active_Card_Expiring_Soon state
                        if (!$smHelper->isStateAlive('Customer_Active_Card_Expiring_Soon')) {
                            if ($smHelper->isStripeCardWillExpire()) {
                                $smHelper->newStateEntry('Customer_Active_Card_Expiring_Soon', '');
                                $smHelper->addStateWithLead();
                                $output->writeln('<info>App enters into Customer_Active_Card_Expiring_Soon</info>');
                            } else {
                                $output->writeln('<info>Stripe Card will not expired</info>');
                            }
                        }
                    }

                    if ($checkStateFurther) {
                        if ($state=$smHelper->isStateAlive('Customer_Inactive_Exit_Cancel')) {
                            if ($smHelper->checkLicenseValiditityWithGracePeriod($state)) {
                                $dtHelper=new DateTimeHelper();
                                $updateOn=$dtHelper->getLocalDateTime();
                                $updateOn=$container->get('mautic.helper.template.date')->toDate($updateOn, 'local'); //$state->getUpdatedOn()
                                $smHelper->makeStateInActive(['Customer_Active']);
                                $smHelper->newStateEntry('Customer_Inactive_Archive', $smHelper->getAlertMessage('le.sm.customer.inactive.archieve.reason', ['%DATE%'=>$updateOn, '%STATE%'=>$state->getState()]));
                                $smHelper->addStateWithLead();
                                $output->writeln('<info>App enters into Customer_Inactive_Archive</info>');
                            }
                        } else {
                            $firstInActiveState=$smHelper->getFirstInActiveState();
                            if ($firstInActiveState) {
                                $updatedOn=$firstInActiveState->getUpdatedOn();
                                $dtHelper =new DateTimeHelper();
                                $diffdays =$dtHelper->getDiff($updatedOn, '%R%a', true);
                                $output->writeln('<info>First InActive state days difference:'.$diffdays.'</info>');
                                if ($diffdays > 30) {
                                    $updateOn=$dtHelper->getLocalDateTime();
                                    $updateOn=$container->get('mautic.helper.template.date')->toDate($updateOn, 'local');
                                    $smHelper->makeStateInActive([$prefix.'_Active']);
                                    $smHelper->newStateEntry($prefix.'_Inactive_Archive', $smHelper->getAlertMessage('le.sm.customer.inactive.archieve.reason', ['%DATE%'=>$updateOn, '%STATE%'=>$firstInActiveState->getState()]));
                                    $smHelper->addStateWithLead();
                                    $output->writeln('<info>App enters into '.$prefix.'_Inactive_Archive</info>');
                                }
                            }
                        }
                    }
                }
                if ($checkStateFurther) {
                    //to check Customer_Inactive_Under_Review state
                    $elasticApiHelper=$container->get('mautic.helper.elasticapi');
                    if (!$smHelper->isStateAlive($prefix.'_Inactive_Under_Review')) {
                        if (!$elasticApiHelper->checkAccountState()) {
                            $smHelper->makeStateInActive([$prefix.'_Active']);
                            $smHelper->newStateEntry($prefix.'_Inactive_Under_Review', '');
                            $smHelper->addStateWithLead();
                            $smHelper->sendInactiveUnderReviewEmail();
                            $output->writeln('<info>App enters into Customer_Inactive_Under_Review</info>');
                        } else {
                            $output->writeln('<info>Elastic Email Account is active</info>');
                        }
                    } else {
                        if ($elasticApiHelper->checkAccountState()) {
                            $smHelper->makeStateInActive([$prefix.'_Inactive_Under_Review']);
                            if (!$smHelper->isAnyInActiveStateAlive()) {
                                $smHelper->newStateEntry($prefix.'_Active', '');
                                $output->writeln('<info>App enters into Customer_Active from Customer_Inactive_Under_Review</info>');
                            }
                            $smHelper->addStateWithLead();
                        }
                    }
                }
            }
            $this->completeRun();

            return 0;
        } catch (\Exception $e) {
            $errormsg= 'General Error:'.$e->getMessage();
            // Something else happened, completely unrelated to Stripe
        }
        if ($errormsg != '') {
            $output->writeln('exception->'.$errormsg."\n");
            $this->completeRun();

            return 0;
        }
    }
}
