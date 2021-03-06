<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\UserBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\UserBundle\Security\Authentication\Token\PluginToken;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class PublicController extends FormController
{
    /**
     * Generates a new password for the user and emails it to them.
     */
    public function passwordResetAction()
    {
        /** @var \Mautic\UserBundle\Model\UserModel $model */
        $model = $this->getModel('user');

        $data     = ['identifier' => ''];
        $action   = $this->generateUrl('le_user_passwordreset');
        $form     = $this->get('form.factory')->create('passwordreset', $data, ['action' => $action]);
        $ismobile = InputHelper::isMobile();
        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            if ($isValid = $this->isFormValid($form)) {
                //find the user
                $data = $form->getData();
                $user = $model->getRepository()->findByIdentifier($data['identifier']);

                //if ($user == null) {
                //    $form['identifier']->addError(new FormError($this->translator->trans('mautic.user.user.passwordreset.nouserfound', [], 'validators')));
                //} else {
                try {
                    $mailer = $this->container->get('le.transport.elasticemail.transactions');
                    $model->sendResetEmail($user, $mailer);
                    $this->addFlash('mautic.user.user.notice.passwordreset', [], 'notice', null, false);
                } catch (\Exception $exception) {
                    $this->addFlash('mautic.user.user.notice.passwordreset.error', [], 'error', null, false);
                }

                return $this->redirect($this->generateUrl('login'));
            //}
            } else {
                $this->addFlash($this->translator->trans('mautic.user.user.passwordreset.nouserfound', [], 'validators'), [], 'error', null, false);

                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $action,
                        'viewParameters'  => [
                            'form'     => $form->createView(),
                            'ismobile' => $ismobile,
                        ],
                        'contentTemplate' => 'MauticUserBundle:Security:reset.html.php',
                    ]
                );
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form'     => $form->createView(),
                'ismobile' => $ismobile,
            ],
            'contentTemplate' => 'MauticUserBundle:Security:reset.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_contact_index',
                'leContent'     => 'lead',
                'route'         => $action,
            ],
        ]);
    }

    public function passwordResetConfirmAction()
    {
        /** @var \Mautic\UserBundle\Model\UserModel $model */
        $model = $this->getModel('user');

        $data   = ['identifier' => '', 'password' => '', 'password_confirm' => ''];
        $action = $this->generateUrl('le_user_passwordresetconfirm');
        $form   = $this->get('form.factory')->create('passwordresetconfirm', [], ['action' => $action]);
        $token  = $this->request->query->get('token');

        if ($token) {
            $this->request->getSession()->set('resetToken', $token);
        }
        $ismobile = InputHelper::isMobile();
        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            if ($isValid = $this->isFormValid($form)) {
                //find the user
                $data = $form->getData();
                /** @var \Mautic\UserBundle\Entity\User $user */
                $user = $model->getRepository()->findByIdentifier($data['identifier']);

                if ($user == null) {
                    $form['identifier']->addError(new FormError($this->translator->trans('mautic.user.user.passwordreset.nouserfound', [], 'validators')));
                } else {
                    if ($this->request->getSession()->has('resetToken')) {
                        $resetToken = $this->request->getSession()->get('resetToken');
                        $encoder    = $this->get('security.encoder_factory')->getEncoder($user);

                        if ($model->confirmResetToken($user, $resetToken)) {
                            $encodedPassword = $model->checkNewPassword($user, $encoder, $data['plainPassword']);
                            if (!empty($data['plainPassword'])) {
                                $apiKey = base64_encode($user->getUsername().':'.$data['plainPassword']);
                                $user->setApiKey($apiKey);
                            }
                            $user->setPassword($encodedPassword);
                            $model->saveEntity($user);

                            $this->addFlash('mautic.user.user.notice.passwordreset.success', [], 'notice', null, false);

                            $this->request->getSession()->remove('resetToken');

                            return $this->redirect($this->generateUrl('login'));
                        } else {
                            $this->addFlash('mautic.user.user.notice.passwordreset.completed', [], 'notice', null, false);

                            return $this->redirect($this->generateUrl('login'));
                        }

                        return $this->delegateView([
                            'viewParameters' => [
                                'form'     => $form->createView(),
                                'ismobile' => $ismobile,
                            ],
                            'contentTemplate' => 'MauticUserBundle:Security:resetconfirm.html.php',
                            'passthroughVars' => [
                                'route' => $action,
                            ],
                        ]);
                    } else {
                        $this->addFlash('mautic.user.user.notice.passwordreset.missingtoken', [], 'notice', null, false);

                        return $this->redirect($this->generateUrl('le_user_passwordresetconfirm'));
                    }
                }
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form'     => $form->createView(),
                'ismobile' => $ismobile,
            ],
            'contentTemplate' => 'MauticUserBundle:Security:resetconfirm.html.php',
            'passthroughVars' => [
                'route' => $action,
            ],
        ]);
    }

    public function redirectLoginAction()
    {
        /** @var \Mautic\UserBundle\Model\UserModel $model */
        $model      = $this->getModel('user');
        $userrepo   =$model->getRepository();
        $users      =$userrepo->findBy(
            [
                'role' => 2,
            ],
            ['id'=> 'ASC'],
            1,
            0
        );
        if (sizeof($users) > 0) {
            $userEntity=$users[0];
            $token     = new PluginToken(
                'main',
                null,
                $userEntity->getUsername(),
                $userEntity->getPassword()
            );
            $authToken = $this->get('security.authentication.manager')->authenticate($token);
            $this->get('security.token_storage')->setToken($token);
            $this->get('session')->set('_security_mautic', serialize($token));
            $this->get('session')->set('isLogin', true);
            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($this->request, $authToken);
                $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            }
        }
//        $redirectUrl=$this->generateUrl('login', [], 0);
//
//        return $this->delegateView([
//            'viewParameters' => [
//                'redirectUrl'     => $redirectUrl,
//            ],
//            'contentTemplate' => 'MauticUserBundle:Security:redirect.html.php',
//            'passthroughVars' => [
//            ],
//        ]);
        return $this->delegateRedirect($this->generateUrl('login'));
    }
}
