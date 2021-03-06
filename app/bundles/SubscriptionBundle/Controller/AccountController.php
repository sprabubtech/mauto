<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SubscriptionBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\SubscriptionBundle\Entity\Account;
use Mautic\SubscriptionBundle\Entity\Billing;
use Mautic\SubscriptionBundle\Entity\StripeCard;

/**
 * Class AccountController.
 */
class AccountController extends FormController
{
    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }

        $paymentrepository  =$this->get('le.subscription.repository.payment');
        $planType           ='Trial';
        $planName           ='';
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null) {
            $planType    ='Paid';
            $planName    = $lastpayment->getPlanName();
        }
        /** @var \Mautic\SubscriptionBundle\Model\AccountInfoModel $model */
        $model         = $this->getModel('subscription.accountinfo');
        $action        = $this->generateUrl('le_accountinfo_action', ['objectAction' => 'edit']);
        $accrepo       = $model->getRepository();
        $accountentity = $accrepo->findAll();
        if (sizeof($accountentity) > 0) {
            $account = $accountentity[0]; //$model->getEntity(1);
        } else {
            $account = new Account();
        }
        $disablepoweredby   = 1;
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null && $lastpayment->getAmount() != 0 && $lastpayment->getPlanName() != 'leplan1') {
            $disablepoweredby = 0;
        }
        $form          = $model->createForm($account, $this->get('form.factory'), $action, ['isPoweredBy' => $disablepoweredby]);
        if ($this->request->getMethod() == 'POST') {
            $isValid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($isValid = $this->isFormValid($form)) {
                    $data           = $this->request->request->get('accountinfo');
                    $accountname    = $data['accountname'];
                    $domainname     = $data['domainname'];
                    $email          = $data['email'];
                    $phonenumber    = $data['phonenumber'];
                    $currencysymbol = $data['currencysymbol'];
                    $timezone       = $data['timezone'];
                    $accountid      = $data['accountid'];
                    if (isset($data['needpoweredby'])) {
                        $needpoweredby = $data['needpoweredby'];
                    } else {
                        $needpoweredby = 1;
                    }
                    $account->setAccountname($accountname);
                    $account->setDomainname($domainname);
                    $account->setEmail($email);
                    $account->setPhonenumber($phonenumber);
                    $account->setCurrencysymbol($currencysymbol);
                    $account->setTimezone($timezone);
                    $account->setAccountid($accountid);
                    $account->setNeedpoweredby($needpoweredby);
                    /** @var \Mautic\CoreBundle\Configurator\Configurator $configurator */
                    $configurator = $this->get('mautic.configurator');
                    $isWritabale  = $configurator->isFileWritable();
                    if ($isWritabale && $timezone != '') {
                        $configurator->mergeParameters(['default_timezone' => $timezone]);
                        $configurator->write();
                    }
                    $model->saveEntity($account);
                }
            }
            if ($cancelled || $isValid) {
                if (!$cancelled && $this->isFormApplied($form)) {
                    return $this->delegateRedirect($this->generateUrl('le_accountinfo_action', ['objectAction' => 'edit']));
                } else {
                    return $this->delegateRedirect($this->generateUrl('le_settingsmenu_action'));
                }
            }
        }
        $tmpl    = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';
        $smHelper=$this->get('le.helper.statemachine');

        return $this->delegateView([
            'viewParameters' => [
                //'tmpl'               => $tmpl,
                'form'                => $form->createView(),
                'security'            => $this->get('mautic.security'),
                'actionRoute'         => 'le_accountinfo_action',
                'typePrefix'          => 'form',
                'planType'            => $planType,
                'planName'            => $planName,
                'isEmailVerified'     => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:form.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'edit']),
            ],
        ]);
    }

    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function billingAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }

        /** @var \Mautic\SubscriptionBundle\Model\BillingModel $model */
        $model         = $this->getModel('subscription.billinginfo');
        $action        = $this->generateUrl('le_accountinfo_action', ['objectAction' => 'billing']);
        $billingrepo   = $model->getRepository();
        $billingentity = $billingrepo->findAll();
        if (sizeof($billingentity) > 0) {
            $billing = $billingentity[0]; //$model->getEntity(1);
        } else {
            $billing = new Billing();
        }
        $form          = $model->createForm($billing, $this->get('form.factory'), $action, ['isBilling' => true]);
        if ($this->request->getMethod() == 'POST') {
            $isValid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($isValid = $this->isFormValid($form)) {
                    $data               = $this->request->request->get('billinginfo');
                    $companyname        = $data['companyname'];
                    $companyaddressname = $data['companyaddress'];
                    $accountingemail    = $data['accountingemail'];
                    $billing->setCompanyname($companyname);
                    $billing->setCompanyaddress($companyaddressname);
                    $billing->setAccountingemail($accountingemail);
                    $model->saveEntity($billing);
                }
            }
            if ($cancelled || $isValid) {
                if (!$cancelled && $this->isFormApplied($form)) {
                    return $this->delegateRedirect($this->generateUrl('le_accountinfo_action', ['objectAction' => 'billing']));
                } else {
                    return $this->delegateRedirect($this->generateUrl('le_settingsmenu_action'));
                }
            }
        }
        $tmpl                  = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';
        $emailModel            =$this->getModel('email');
        $statrepo              =$emailModel->getStatRepository();
        $licenseinfo           =$this->get('mautic.helper.licenseinfo')->getLicenseEntity();
        $licensestart          =$licenseinfo->getLicenseStart();
        $licenseend            =$licenseinfo->getLicenseEnd();
        $contactUsage          =$licenseinfo->getActualRecordCount();
        $totalContactCredits   =$licenseinfo->getTotalRecordCount();
        $totalEmailCredits     =$licenseinfo->getTotalEmailCount();
        $actualEmailCredits    = $licenseinfo->getActualEmailCount();
        $currentDate           = date('Y-m-d');
        $monthStartDate        = date('Y-m-01');
        $emailValidityEndDate  = $this->get('mautic.helper.licenseinfo')->getEmailValidityEndDate();
        $emailValidityEndDays  = round((strtotime($emailValidityEndDate) - strtotime($currentDate)) / 86400);
        $emailUsage            =$statrepo->getSentCountsByDate($monthStartDate);
        $trialEndDays          =$this->get('mautic.helper.licenseinfo')->getLicenseRemainingDays();
        $planLabel             ='Free Trial';
        $paymentrepository     =$this->get('le.subscription.repository.payment');
        $lastpayment           =$paymentrepository->getLastPayment();
        $datehelper            =$this->get('mautic.helper.template.date');
        $validityTill          =$datehelper->toDate($licenseend);
        $planAmount            ='';
        $custplanamount        = '';
        $planName              = '';
        $planType              = 'Trial';
        if ($lastpayment != null) {
            $planLabel       = $lastpayment->getPlanLabel();
            $validityTill    =$datehelper->toDate($lastpayment->getValidityTill());
            $planAmount      = $lastpayment->getCurrency().$lastpayment->getAmount();
            $custplanamount  = $planAmount;
            if ($lastpayment->getPlanName() == 'leplan2') {
                $custplanamount = ((($totalEmailCredits - 100000) / 10000) * 10) + 49;
                $custplanamount = $lastpayment->getCurrency().$custplanamount;
            }
            $planType       = 'Paid';
            $planName       = $lastpayment->getPlanName();
        }
        $smHelper = $this->get('le.helper.statemachine');

        return $this->delegateView([
            'viewParameters' => [
                //'tmpl'               => $tmpl,
                'form'                => $form->createView(),
                'security'            => $this->get('mautic.security'),
                'actionRoute'         => 'le_accountinfo_action',
                'typePrefix'          => 'form',
                'emailUsage'          => $emailUsage,
                'contactUsage'        => $contactUsage,
                'planType'            => $planType,
                'vallidityTill'       => $validityTill,
                'planAmount'          => $planAmount,
                'trialEndDays'        => $trialEndDays.'',
                'totalContactCredits' => $totalContactCredits,
                'totalEmailCredits'   => $totalEmailCredits,
                'custplanamount'      => $custplanamount,
                'planName'            => $planName,
                'actualEmailCredits'  => $actualEmailCredits,
                'planLabel'           => $planLabel,
                'isEmailVerified'     => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:billing.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'billing']),
            ],
        ]);
    }

    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function paymentAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }
        $planName           = '';
        $tmpl               = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';
        $paymentrepository  =$this->get('le.subscription.repository.payment');
        $planType           ='Trial';
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null) {
            $planType    ='Paid';
            $planName    = $lastpayment->getPlanName();
        }
        $paymentalias       =$paymentrepository->getTableAlias();
        $filter             = [
            'force'  => [
                ['column' => $paymentalias.'.paymentstatus', 'expr' => 'neq', 'value' => 'Initiated'],
            ],
        ];
        $args= [
            'filter'         => $filter,
            'orderBy'        => $paymentalias.'.id',
            'orderByDir'     => 'DESC',
         //   'ignore_paginator' => true,
        ];
        $payments =$paymentrepository->getEntities($args);
        $smHelper = $this->get('le.helper.statemachine');

        return $this->delegateView([
            'viewParameters' => [
                //'tmpl'               => $tmpl,
                'security'           => $this->get('mautic.security'),
                'actionRoute'        => 'le_accountinfo_action',
                'typePrefix'         => 'form',
                'payments'           => $payments,
                'planType'           => $planType,
                'planName'           => $planName,
                'isEmailVerified'    => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:payment.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'payment']),
            ],
        ]);
    }

    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function cancelAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }
        $paymentrepository    =$this->get('le.subscription.repository.payment');
        $appStatus            = $this->get('mautic.helper.licenseinfo')->getAppStatus();
        $recordCount          = $this->get('mautic.helper.licenseinfo')->getTotalRecordCount();
        $contactcount         = $this->get('mautic.helper.licenseinfo')->getActualRecordCount();
        $emailcount           = $this->get('mautic.helper.licenseinfo')->getActualEmailCount();
        $licenseEndDate       = $this->get('mautic.helper.licenseinfo')->getLicenseEndDate();
        $licenseRemDays       = $this->get('mautic.helper.licenseinfo')->getLicenseRemainingDays();
        $subcancel            = $this->get('mautic.helper.licenseinfo')->getCancelDate();
        $subcanceldate        = date('F d, Y', strtotime($subcancel));
        $datehelper           =$this->get('mautic.helper.template.date');
        $planName             = '';
        if ($recordCount == 'UL') {
            $recordCount= 'unlimited';
        } else {
            $recordCount = number_format($recordCount);
        }
        $planType           = 'Trial';
        $planLabel          = 'Free Trail';
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null) {
            $planType    ='Paid';
            $planName    = $lastpayment->getPlanName();
            $planLabel   = $lastpayment->getPlanLabel();
        }
        $license='';
        if ($planType == 'Trial') {
            $license = $licenseRemDays.' days';
        } else {
            if ($lastpayment != null) {
                $planType    ='Paid';
                $license     = $datehelper->toDate($lastpayment->getValidityTill());
            }
            $license = date('F d, Y', strtotime($license.' + 1 days'));
        }
        $tmpl          = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';
        $smHelper      = $this->get('le.helper.statemachine');
        $isCancelled   =$smHelper->isStateAlive('Customer_Inactive_Exit_Cancel');

        return $this->delegateView([
            'viewParameters' => [
               // 'tmpl'               => $tmpl,
                'security'             => $this->get('mautic.security'),
                'actionRoute'          => 'le_accountinfo_action',
                'typePrefix'           => 'form',
                'isCancelled'          => $isCancelled,
                'recordcount'          => $recordCount,
                'licenseenddate'       => $license,
                'planType'             => $planType,
                'planName'             => $planName,
                'canceldate'           => $subcanceldate,
                'planLabel'            => $planLabel,
                'contactcount'         => $contactcount,
                'emailcount'           => $emailcount,
                'isEmailVerified'      => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:cancel.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'cancel']),
            ],
        ]);
    }

    /**
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function cardinfoAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }
        $paymentrepository  =$this->get('le.subscription.repository.payment');
        $planType           ='Trial';
        $planName           ='';
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null) {
            $planType    ='Paid';
            $planName    = $lastpayment->getPlanName();
        }
        $tmpl               = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';
        $stripecardrepo     =$this->get('le.subscription.repository.stripecard');
        $stripecards        = $stripecardrepo->findAll();
        if (sizeof($stripecards) > 0) {
            $stripecard = $stripecards[0];
        } else {
            $stripecard = new StripeCard();
        }
        $paymenthelper     =$this->get('le.helper.payment');
        $smHelper          = $this->get('le.helper.statemachine');

        return $this->delegateView([
            'viewParameters' => [
               // 'tmpl'               => $tmpl,
                'security'           => $this->get('mautic.security'),
                'actionRoute'        => 'le_accountinfo_action',
                'typePrefix'         => 'form',
                'stripecard'         => $stripecard,
                'letoken'            => $paymenthelper->getUUIDv4(),
                'planType'           => $planType,
                'planName'           => $planName,
                'lastpayment'        => $lastpayment,
                'isEmailVerified'    => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:cardinfo.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'cardinfo']),
            ],
        ]);
    }

    public function senderreputationAction()
    {
        if (!$this->user->isAdmin() && !$this->user->isCustomAdmin() && $this->coreParametersHelper->getParameter('accountinfo_disabled')) {
            return $this->accessDenied();
        }
        $paymentrepository  =$this->get('le.subscription.repository.payment');
        $planType           ='Trial';
        $planName           ='';
        $lastpayment        = $paymentrepository->getLastPayment();
        if ($lastpayment != null) {
            $planType    ='Paid';
            $planName    = $lastpayment->getPlanName();
        }
        $elasticApiHelper = $this->get('mautic.helper.elasticapi');
        $smHelper         = $this->get('le.helper.statemachine');

        return $this->delegateView([
            'viewParameters' => [
                // 'tmpl'               => $tmpl,
                'security'           => $this->get('mautic.security'),
                'actionRoute'        => 'le_accountinfo_action',
                'typePrefix'         => 'form',
                'emailreputations'   => $elasticApiHelper->getReputationDetails(),
                'planType'           => $planType,
                'planName'           => $planName,
                'isEmailVerified'    => $smHelper->isStateAlive('Trial_Unverified_Email'),
            ],
            'contentTemplate' => 'MauticSubscriptionBundle:AccountInfo:senderreputation.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_accountinfo_index',
                'leContent'     => 'accountinfo',
                'route'         => $this->generateUrl('le_accountinfo_action', ['objectAction' => 'senderreputation']),
            ],
        ]);
    }
}
