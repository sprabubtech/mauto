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
use Mautic\SubscriptionBundle\Entity\Billing;
use Mautic\SubscriptionBundle\Helper\PaymentHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePaymentCommand extends ModeratedCommand
{
    protected function configure()
    {
        $this
            ->setName('le:payment:update')
            ->setAliases(['le:payment:update'])
            ->setDescription('Update payment based on last payment validity end')
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
            $paymentrepository  =$container->get('le.subscription.repository.payment');
            $lastpayment        =$paymentrepository->getLastPayment();
            $smHelper           =$container->get('le.helper.statemachine');
            if ($smHelper->isStateAlive('Customer_Inactive_Exit_Cancel')) {
                $output->writeln('<info>'.'Account is requested for cancellation.So payment will not check.'.'</info>');

                return 0;
            } elseif ($activeState=$smHelper->isStateAlive('Customer_Active')) {
                $validitytill=$lastpayment->getValidityTill();
                $dtHelper1   =new DateTimeHelper($activeState->getUpdatedOn());
                $output->writeln('<info>'.$dtHelper1->getString('Y-m-d').'</info>');
                if (strtotime($validitytill) < strtotime($dtHelper1->getString('Y-m-d'))) {
                    $dtHelper2=new DateTimeHelper();
                    $diffdays =$dtHelper2->getDiff($activeState->getUpdatedOn(), '%R%a', true);
                    if ($diffdays < 4) {
                        $output->writeln('<info>'.'Account moves into active state on '.$diffdays.' days before only.Payment will process after 3 days.</info>');

                        return 0;
                    }
                }
            } elseif (!$smHelper->isStateAlive('Trial_Active')) {
                $output->writeln('<info>'.'Account is not active to proceed further.'.'</info>');

                return 0;
            }
            $translator = $container->get('translator');
            // $translator->trans('mautic.campaign.rebuild.leads_affected', ['%leads%' => $processed])
            $licenseinfohelper  =$container->get('mautic.helper.licenseinfo');
            $licenseinfo        =$licenseinfohelper->getLicenseEntity();
            // $accountStatus      =$licenseinfo->getAppStatus();
//            if ($accountStatus != 'Active') {
//                $output->writeln('<info>'.'Account is not active to proceed further.'.'</info>');
//
//                return 0;
//            }
            if ($lastpayment != null) {
                $appValidityRenewal=true;
                $isRetry           =$lastpayment->getPaymentStatus() != 'Paid';
                $stripecardrepo    = $container->get('le.subscription.repository.stripecard');
                $stripecards       = $stripecardrepo->findAll();
                $stripecard        = null;
                if (sizeof($stripecards) > 0) {
                    $stripecard = $stripecards[0];
                }
                $paymenthelper     =$container->get('le.helper.payment');
                if (!$isRetry) {
                    $firstpayment      = $paymentrepository->getFirstPayment();
                    $dateHelper        = $container->get('mautic.helper.template.date');
                    if ($licenseinfo != null) {
                        $totalrecordcount =$licenseinfo->getTotalEmailCount();
                        $actualrecordcount=$licenseinfo->getActualEmailCount();
                        $validitytill     = $lastpayment->getValidityTill();
                        $currentdate      = date('Y-m-d');
                        $planname         = $lastpayment->getPlanName();
                        $planamount       = $lastpayment->getAmount();
                        $plancredits      = $lastpayment->getBeforeCredits();
                        $firstPaymentdate = $firstpayment->getcreatedOn();
                        $firstPaymentdate = $dateHelper->toCustDate($firstPaymentdate, 'local', 'Y-m-d');
                        $monthcount       = 1;
                        $plancredits      = 'UL';
                        $plancredits      = $translator->trans('le.pricing.plan.email.credits.'.$planname);
                        if ($stripecard != null) {
                            $ismoreusage=false;
                            if ($totalrecordcount != 'UL' && $totalrecordcount < $actualrecordcount) {
                                $ismoreusage=true;
                            }
                            $isvalidityexpired=false;
                            if (strtotime($validitytill) < strtotime($currentdate)) {
                                $isvalidityexpired=true;
                            }
                            if ($ismoreusage || $isvalidityexpired) {
                                $output->writeln('<info>'.'Total Record Count:'.$totalrecordcount.'</info>');
                                $output->writeln('<info>'.'Actual Record Count:'.$actualrecordcount.'</info>');
//                            $multiplx=1;
//                            if ($actualrecordcount > 0) {
//                                $multiplx   = ceil($actualrecordcount / 10000);
//                                $multiplx   = $multiplx - 10;
//                            }
                                if ($isvalidityexpired) {
                                    //$monthdiff  =$this->getMonthDiff($firstPaymentdate, $currentdate);
                                    //$planamount = 0;
                                    //if ($monthdiff == 3) {
                                    //$planname   = 'leplan1';
                                    $planamount = $translator->trans('le.pricing.plan.amount.'.$planname);
                                    //}
                                    $curtotalcount  = $totalrecordcount - $plancredits;
                                    $curactualcount = $actualrecordcount - $plancredits;
                                    if ($curactualcount < 0) {
                                        $curactualcount = 0;
                                    }
                                    $addoncredits         = $curtotalcount - $curactualcount;
                                    $netamount            = (($planamount)); // + (10 * $multiplx));
                                    $netcredits           = (($plancredits + $addoncredits)); // + (5000 * $multiplx));
                                    $validitytill         =date('Y-m-d', strtotime('-1 day +'.$monthcount.' months'));
                                } elseif ($ismoreusage) {
                                    $addoncredits = $translator->trans('le.pricing.plan.addon.credits.'.$planname);
                                    $addonAmount  = $translator->trans('le.pricing.plan.addon.amount.'.$planname);
                                    //$amount1   =$this->getProrataAmount($currentdate, $validitytill, $lastamount);
                                    $excesscount=$actualrecordcount - $totalrecordcount;
                                    $amtmultiplx=1;
                                    if ($excesscount > 0) {
                                        $amtmultiplx   =ceil($excesscount / $addoncredits);
                                    }
                                    $examount             = $addonAmount;
                                    $netamount            = ($examount * $amtmultiplx);
                                    $netcredits           = (($totalrecordcount) + ($addoncredits * $amtmultiplx));
                                    $appValidityRenewal   = false;
                                    //$netamount   =$this->getProrataAmount($output, $currentdate, $validitytill, $netamount);
                                    //$output->writeln('<info>'.'Refund Amount:'.$amount1.'</info>');
                                    // $output->writeln('<info>'.'Charged Amount:'.$amount2.'</info>');
                                    // $netamount=$amount2 - $amount1;
                                    $output->writeln('<info>'.'Net Amount:'.$netamount.'</info>');
                                }
                                $contactcredites    = $translator->trans('le.pricing.plan.contact.credits.'.$planname);
                                if ($netamount > 0) {
                                    $this->attemptStripeCharge($output, $stripecard, $paymenthelper, $paymentrepository, $planname, $planamount, $plancredits, $netamount, $netcredits, $validitytill, $appValidityRenewal, $contactcredites, false);
                                    $smHelper->sendInternalSlackMessage($appValidityRenewal ? 'subscription_payment_received' : 'addon_payment_received');
                                } else {
                                    $subsrepository=$container->get('le.core.repository.subscription');
                                    $subsrepository->updateContactCredits($contactcredites, $validitytill, $currentdate, $appValidityRenewal, $plancredits);
                                    $paymentrepository->captureStripePayment($firstpayment->getOrderID(), $firstpayment->getPaymentID(), $planamount, $netamount, $plancredits, $plancredits, $validitytill, $planname, null, null, 'Paid', $appValidityRenewal);
                                    $output->writeln('<error>'.'Amount is too less to charge:'.$netamount.'</error>');
                                }
                            } else {
                                $output->writeln('<info>'."Plan validity available upto $validitytill".'</info>');
                            }
                        } else {
                            $output->writeln('<error>'.'Customer Credit Card details not found.'.'</error>');
                        }
                    } else {
                        $output->writeln('<error>'.'License info details not found.'.'</error>');
                    }
                } else {
                    if ($lastpayment->getTaxamount() == 1) {//add on renewal
                        $appValidityRenewal=false;
                    }
                    $contactcredites    = $translator->trans('le.pricing.plan.contact.credits.'.$lastpayment->getPlanName());
                    $this->attemptStripeCharge($output, $stripecard, $paymenthelper, $paymentrepository, $lastpayment->getPlanName(), $lastpayment->getAmount(), $lastpayment->getBeforeCredits(), $lastpayment->getNetamount(), $lastpayment->getAfterCredits(), $lastpayment->getValidityTill(), $appValidityRenewal, $contactcredites, true, $lastpayment);
                    $smHelper->sendInternalSlackMessage($appValidityRenewal ? 'subscription_payment_received' : 'addon_payment_received');
                }
            } else {
                $output->writeln('<error>'.'Last payment details not found.'.'</error>');
//                $output->writeln(
//                    '<comment>'."LeadsEngage Comment".'</comment>'."\n"
//                );
            }
            $this->completeRun();

            return 0;
        } catch (\Stripe\Error\Card $e) {
            // Since it's a decline, \Stripe\Error\Card will be caught
            $body = $e->getJsonBody();
            $err  = $body['error'];

            //  print('Status is:' . $e->getHttpStatus() . "\n");
            // print('Type is:' . $err['type'] . "\n");
            //print('Code is:' . $err['code'] . "\n");
            // param is '' in this case
            //  print('Param is:' . $err['param'] . "\n");
            // print('Message is:' . $err['message'] . "\n");
            $errormsg      ='Card Error:'.$err['message'];
            $carderror     = true;
            $payment       =$paymentrepository->captureStripePayment('', '', $planamount, $netamount, $plancredits, $netcredits, $validitytill, $planname, null, null, $errormsg, $appValidityRenewal);
            if ($err['code'] != 'processing_error') {
                $smHelper=$container->get('le.helper.statemachine');
                $this->updatePaymentFailureState($smHelper, $errormsg, $appValidityRenewal, $output);
            }
            // $licenseinfohelper->suspendApplication();
        } catch (\Stripe\Error\RateLimit $e) {
            $errormsg= 'Too many requests made to the API too quickly';
            // Too many requests made to the API too quickly
        } catch (\Stripe\Error\InvalidRequest $e) {
            $errormsg= "Invalid parameters were supplied to Stripe's API->".$e->getMessage();
            // Invalid parameters were supplied to Stripe's API
        } catch (\Stripe\Error\Authentication $e) {
            $errormsg= "Authentication with Stripe's API failed";
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
        } catch (\Stripe\Error\ApiConnection $e) {
            $errormsg= 'Network communication with Stripe failed';
            // Network communication with Stripe failed
        } catch (\Stripe\Error\Base $e) {
            $errormsg= 'Display a very generic error to the user, and maybe send->'.$e->getMessage();
            // Display a very generic error to the user, and maybe send
            // yourself an email
        } catch (\Exception $e) {
            $errormsg= 'General Error:'.$e->getMessage();
            // Something else happened, completely unrelated to Stripe
        }
        if ($errormsg != '') {
            $smHelper          = $container->get('le.helper.statemachine');
            if (!$carderror) {
                $smHelper->sendInternalSlackMessage('payment_failed_internal_action_needed');
            }
            $output->writeln('exception->'.$errormsg."\n");
            $this->completeRun();

            return 0;
        }
    }

    protected function attemptStripeCharge($output, $stripecard, PaymentHelper $paymenthelper, $paymentrepository, $planname, $planamount, $plancredits, $netamount, $netcredits, $validitytill, $appValidityRenewal, $contactcredites, $isRetry, $lastPayment=null)
    {
        $container  = $this->getContainer();
        $apikey     =$container->get('mautic.helper.core_parameters')->getParameter('stripe_api_key');
        \Stripe\Stripe::setApiKey($apikey);
        $charges = \Stripe\Charge::create([
            'amount'   => $netamount * 100, //100 cents = 1 dollar
            'currency' => 'usd',
            //"source" => $token, // obtained with Stripe.js
            'customer'             => $stripecard->getCustomerID(),
            'description'          => 'charge for anyfunnels product purchase',
            'capture'              => true,
            'statement_descriptor' => 'anyfunnels purchase',
        ], [
            'idempotency_key' => $paymenthelper->getUUIDv4(),
        ]);
        if (isset($charges)) {
            $chargeid             = $charges->id;
            $status               = $charges->status;
            $failure_code         = $charges->failure_code;
            $failure_message      = $charges->failure_message;
            if ($status == 'succeeded') {
                $todaydate     = date('Y-m-d');
                if (!$isRetry) {
                    $orderid              = uniqid();
                    $payment              =$paymentrepository->captureStripePayment($orderid, $chargeid, $planamount, $netamount, $plancredits, $netcredits, $validitytill, $planname, null, null, 'Paid', $appValidityRenewal);
                } else {
                    $lastPayment->setPaymentID($chargeid);
                    $lastPayment->setPaymentStatus('Paid');
                    $paymentrepository->saveEntity($lastPayment);
                    $payment=$lastPayment;
                }
                $subsrepository=$container->get('le.core.repository.subscription');
                $subsrepository->updateContactCredits($contactcredites, $validitytill, $todaydate, $appValidityRenewal, $netcredits);
                $output->writeln('<info>'.'Plan Renewed Successfully'.'</info>');
                $output->writeln('<info>'.'Transaction ID:'.$chargeid.'</info>');
                $output->writeln('<info>'.'Amount($):'.$netamount.'</info>');
                $output->writeln('<info>'.'Contact Credits:'.$netcredits.'</info>');
                $smHelper=$container->get('le.helper.statemachine');
                $this->updatePaymentSuccessState($smHelper, $output);
                $billingmodel  = $container->get('mautic.model.factory')->getModel('subscription.billinginfo');
                $billingrepo   = $billingmodel->getRepository();
                $billingentity = $billingrepo->findAll();
                if (sizeof($billingentity) > 0) {
                    $billing = $billingentity[0]; //$model->getEntity(1);
                } else {
                    $billing = new Billing();
                }
                if ($billing->getAccountingemail() != '') {
                    $mailer       = $container->get('le.transport.elasticemail.transactions');
                    $paymenthelper=$container->get('le.helper.payment');
                    $paymenthelper->sendPaymentNotification($payment, $billing, $mailer);
                }
                // $subsrepository->updateAppStatus($domain, 'Active');
            } else {
                if (!$isRetry) {
                    $orderid              = uniqid();
                    $paymentrepository->captureStripePayment($orderid, $chargeid, $planamount, $netamount, $plancredits, $netcredits, $validitytill, $planname, null, null, $status, $appValidityRenewal);
                    //$subsrepository=$container->get('le.core.repository.subscription');
                    // $subsrepository->updateAppStatus($domain, 'InActive');
                    $errormsg="Failure Code:$failure_code,Failure Message:$failure_message";
                    $smHelper=$container->get('le.helper.statemachine');
                    $this->updatePaymentFailureState($smHelper, $errormsg, $appValidityRenewal, $output);
                }
                $mailer = $container->get('le.transport.elasticemail.transactions');
                $paymenthelper->paymentFailedEmailtoUser($mailer, $planname);
                $output->writeln('<error>'.'Plan renewed failed due to some technical issues.'.'</error>');
                $output->writeln('<error>'.'Failure Code:'.$failure_code.'</error>');
                $output->writeln('<error>'.'Failure Message:'.$failure_message.'</error>');
            }
        } else {
            $output->writeln('<error>'.'Plan renewed failed due to some technical issues.'.'</error>');
        }
    }

    protected function updatePaymentFailureState($smHelper, $errormsg, $appValidityRenewal, $output)
    {
        if (!$smHelper->isStateAlive('Customer_Inactive_Payment_Issue')) {
            if ($appValidityRenewal) {
                $errormsg='Validity Renewal,'.$errormsg;
            } else {
                $errormsg='AddOn Renewal,'.$errormsg;
            }
            $smHelper->makeStateInActive(['Customer_Active']);
            $smHelper->newStateEntry('Customer_Inactive_Payment_Issue', $errormsg);
            $smHelper->addStateWithLead();
            $output->writeln('<info>App enters into Customer_Inactive_Payment_Issue</info>');
            $smHelper->sendInacitvePaymentIssueEmail();
            $smHelper->sendInternalSlackMessage('payment_failed_customer_action_needed');
        }
    }

    protected function updatePaymentSuccessState($smHelper, $output)
    {
        if ($smHelper->isStateAlive('Customer_Inactive_Payment_Issue')) {
            $smHelper->makeStateInActive(['Customer_Inactive_Payment_Issue']);
            if (!$smHelper->isAnyInActiveStateAlive()) {
                $smHelper->newStateEntry('Customer_Active', '');
                $output->writeln('<info>App enters into Customer_Active</info>');
            }
            $smHelper->addStateWithLead();
        }
    }

    protected function getProrataAmount($output, $start, $end, $amount)
    {
        $date1        = new \DateTime($start);
        $date2        = new \DateTime($end);
        $diff         = $date2->diff($date1)->format('%a');
        $diff         = $diff + 1;
        $output->writeln('<info>'.'Billing Days:'.$diff.'</info>');
        $prorataamount=$amount * ($diff / 31);

        return round($prorataamount);
    }

    protected function getMonthDiff($date1, $date2)
    {
        $ts1 = strtotime($date1);
        $ts2 = strtotime($date2);

        $year1 = date('Y', $ts1);
        $year2 = date('Y', $ts2);

        $month1 = date('m', $ts1);
        $month2 = date('m', $ts2);

        return $diff = (($year2 - $year1) * 12) + ($month2 - $month1);
    }
}
