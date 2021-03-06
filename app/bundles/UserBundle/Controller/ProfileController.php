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
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ProfileController.
 */
class ProfileController extends FormController
{
    /**
     * Generate's account profile.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        //get current user
        $me    = $this->get('security.token_storage')->getToken()->getUser();
        $model = $this->getModel('user');

        //set some permissions
        $permissions = [
            'apiAccess' => ($this->get('mautic.helper.core_parameters')->getParameter('api_enabled')) ?
                $this->get('mautic.security')->isGranted('api:access:full')
                : 0,
            'editName'     => $this->get('mautic.security')->isGranted('user:profile:editname'),
            'editUsername' => $this->get('mautic.security')->isGranted('user:profile:editusername'),
            'editPosition' => $this->get('mautic.security')->isGranted('user:profile:editposition'),
            'editEmail'    => $this->get('mautic.security')->isGranted('user:profile:editemail'),
        ];

        $action = $this->generateUrl('le_user_account');
        $form   = $model->createForm($me, $this->get('form.factory'), $action, ['in_profile' => true]);

        $overrides = [];

        //make sure this user has access to edit privileged fields
        foreach ($permissions as $permName => $hasAccess) {
            if ($permName == 'apiAccess') {
                continue;
            }

            if (!$hasAccess) {
                //set the value to its original
                switch ($permName) {
                    case 'editName':
                        $overrides['firstName'] = $me->getFirstName();
                        $overrides['lastName']  = $me->getLastName();
                        $form->remove('firstName');
                        $form->add(
                            'firstName_unbound',
                            'text',
                            [
                                'label'      => 'mautic.core.firstname',
                                'label_attr' => ['class' => 'control-label'],
                                'attr'       => ['class' => 'form-control'],
                                'mapped'     => false,
                                'disabled'   => true,
                                'data'       => $me->getFirstName(),
                                'required'   => false,
                            ]
                        );

                        $form->remove('lastName');
                        $form->add(
                            'lastName_unbound',
                            'text',
                            [
                                'label'      => 'mautic.core.lastname',
                                'label_attr' => ['class' => 'control-label'],
                                'attr'       => ['class' => 'form-control'],
                                'mapped'     => false,
                                'disabled'   => true,
                                'data'       => $me->getLastName(),
                                'required'   => false,
                            ]
                        );
                        break;

                    case 'editUsername':
                        $overrides['username'] = $me->getUsername();
                        $form->remove('username');
                        $form->add(
                            'username_unbound',
                            'text',
                            [
                                'label'      => 'mautic.core.username',
                                'label_attr' => ['class' => 'control-label'],
                                'attr'       => ['class' => 'form-control'],
                                'mapped'     => false,
                                'disabled'   => true,
                                'data'       => $me->getUsername(),
                                'required'   => false,
                            ]
                        );
                        break;
                    case 'editPosition':
                        $overrides['position'] = $me->getPosition();
                        $form->remove('position');
                        $form->add(
                            'position_unbound',
                            'text',
                            [
                                'label'      => 'mautic.core.position',
                                'label_attr' => ['class' => 'control-label'],
                                'attr'       => ['class' => 'form-control'],
                                'mapped'     => false,
                                'disabled'   => true,
                                'data'       => $me->getPosition(),
                                'required'   => false,
                            ]
                        );
                        break;
                    case 'editEmail':
                        $overrides['email'] = $me->getEmail();
                        $form->remove('email');
                        $form->add(
                            'email_unbound',
                            'text',
                            [
                                'label'      => 'mautic.core.type.email',
                                'label_attr' => ['class' => 'control-label'],
                                'attr'       => ['class' => 'form-control'],
                                'mapped'     => false,
                                'disabled'   => true,
                                'data'       => $me->getEmail(),
                                'required'   => false,
                            ]
                        );
                        break;
                }
            }
        }

        //Check for a submitted form and process it
        $submitted = $this->get('session')->get('formProcessed', 0);
        if ($this->request->getMethod() == 'POST' && !$submitted) {
            $this->get('session')->set('formProcessed', 1);

            //check to see if the password needs to be rehashed
            $submittedPassword     = $this->request->request->get('user[plainPassword][password]', null, true);
            $encoder               = $this->get('security.encoder_factory')->getEncoder($me);
            $overrides['password'] = $model->checkNewPassword($me, $encoder, $submittedPassword);
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($this->isFormValid($form)) {
                    foreach ($overrides as $k => $v) {
                        $func = 'set'.ucfirst($k);
                        $me->$func($v);
                    }

                    $image = $form['preferred_profile_image']->getData();
                    if ($image == 'custom') {
                        /* @var UploadedFile $file */
                        $this->uploadAvatar();
                    }
                    //form is valid so process the data
                    $model->saveEntity($me);

                    //check if the user's locale has been downloaded already, fetch it if not
                    $installedLanguages = $this->get('mautic.helper.core_parameters')->getParameter('supported_languages');

                    if ($me->getLocale() && !array_key_exists($me->getLocale(), $installedLanguages)) {
                        /** @var \Mautic\CoreBundle\Helper\LanguageHelper $languageHelper */
                        $languageHelper = $this->get('mautic.helper.language');

                        $fetchLanguage = $languageHelper->extractLanguagePackage($me->getLocale());

                        // If there is an error, we need to reset the user's locale to the default
                        if ($fetchLanguage['error']) {
                            $me->setLocale(null);
                            $model->saveEntity($me);
                            $message     = 'mautic.core.could.not.set.language';
                            $messageVars = [];

                            if (isset($fetchLanguage['message'])) {
                                $message = $fetchLanguage['message'];
                            }

                            if (isset($fetchLanguage['vars'])) {
                                $messageVars = $fetchLanguage['vars'];
                            }

                            $this->addFlash($message, $messageVars);
                        }
                    }

                    // Update timezone and locale
                    $tz = $me->getTimezone();
                    if (empty($tz)) {
                        $tz = $this->get('mautic.helper.core_parameters')->getParameter('default_timezone');
                    }
                    $this->get('session')->set('_timezone', $tz);

                    $locale = $me->getLocale();
                    if (empty($locale)) {
                        $locale = $this->get('mautic.helper.core_parameters')->getParameter('locale');
                    }
                    $this->get('session')->set('_locale', $locale);

                    $returnUrl = $this->generateUrl('le_user_account');

                    return $this->postActionRedirect(
                        [
                            'returnUrl'       => $returnUrl,
                            'contentTemplate' => 'MauticUserBundle:Profile:index',
                            'passthroughVars' => [
                                'leContent' => 'user',
                            ],
                            'flashes' => [ //success
                                [
                                    'type' => 'notice',
                                    'msg'  => 'mautic.user.account.notice.updated',
                                ],
                            ],
                        ]
                    );
                }
            } else {
                return $this->redirect($this->generateUrl('le_dashboard_index'));
            }
        }
        $this->get('session')->set('formProcessed', 0);

        $parameters = [
            'permissions'       => $permissions,
            'me'                => $me,
            'userForm'          => $form->createView(),
            'authorizedClients' => $this->forward('MauticApiBundle:Client:authorizedClients')->getContent(),
        ];

        return $this->delegateView(
            [
                'viewParameters'  => $parameters,
                'contentTemplate' => 'MauticUserBundle:Profile:index.html.php',
                'passthroughVars' => [
                    'route'         => $this->generateUrl('le_user_account'),
                    'leContent'     => 'user',
                ],
            ]
        );
    }

    /**
     * Upload an asset.
     */
    private function uploadAvatar()
    {
        //get current user
        $user    = $this->get('security.token_storage')->getToken()->getUser();

        $file      = $this->request->files->get('user[custom_avatar]', null, true);
        $avatarDir = $this->get('mautic.helper.template.avatar')->getUserAvatarPath(true);

        if (!file_exists($avatarDir)) {
            mkdir($avatarDir);
        }

        if ($file != null) {
            $file->move($avatarDir, 'avatar'.$user->getId());
        }

        //remove the file from request
        $this->request->files->remove('user');
    }
}
