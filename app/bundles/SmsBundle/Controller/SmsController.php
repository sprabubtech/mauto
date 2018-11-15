<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use Mautic\SmsBundle\Entity\Sms;
use Mautic\SmsBundle\Form\Type\ExampleSendType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SmsController extends FormController
{
    use EntityContactsTrait;

    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        /** @var \Mautic\SmsBundle\Model\SmsModel $model */
        $model = $this->getModel('sms');

        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(
            [
                'sms:smses:viewown',
                'sms:smses:viewother',
                'sms:smses:create',
                'sms:smses:editown',
                'sms:smses:editother',
                'sms:smses:deleteown',
                'sms:smses:deleteother',
                'sms:smses:publishown',
                'sms:smses:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['sms:smses:viewown'] && !$permissions['sms:smses:viewother']) {
            return $this->accessDenied();
        }

        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }
        $listFilters = [
            'filters' => [
                'placeholder' => $this->get('translator')->trans('le.category.filter.placeholder'),
                'multiple'    => true,
            ],
        ];
        // Reset available groups
        $listFilters['filters']['groups'] = [];

        $session = $this->get('session');

        //set limits
        $limit = $session->get('mautic.sms.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get('mautic.sms.filter', ''));
        $session->set('mautic.sms.filter', $search);

        $filter = ['string' => $search, 'force' => []];

        if (!$permissions['sms:smses:viewother']) {
            $filter['force'][] =
                [
                    'column' => 'e.createdBy',
                    'expr'   => 'eq',
                    'value'  => $this->user->getId(),
                ];
        }
        $listFilters['filters']['groups']['mautic.core.filter.category'] = [
            'options' => $this->getModel('category.category')->getLookupResults('sms'),
            'prefix'  => 'category',
        ];
        $updatedFilters = $this->request->get('filters', false);

        if ($updatedFilters) {
            // Filters have been updated

            // Parse the selected values
            $newFilters     = [];
            $updatedFilters = json_decode($updatedFilters, true);

            if ($updatedFilters) {
                foreach ($updatedFilters as $updatedFilter) {
                    list($clmn, $fltr) = explode(':', $updatedFilter);

                    $newFilters[$clmn][] = $fltr;
                }

                $currentFilters = $newFilters;
            } else {
                $currentFilters = [];
            }
        }
        $this->get('session')->set('mautic.form.filter', []);

        if (!empty($currentFilters)) {
            $catIds = [];
            foreach ($currentFilters as $type => $typeFilters) {
                switch ($type) {
                    case 'category':
                        $key = 'categories';
                        break;
                }

                $listFilters['filters']['groups']['mautic.core.filter.'.$key]['values'] = $typeFilters;

                foreach ($typeFilters as $fltr) {
                    switch ($type) {
                        case 'category':
                            $catIds[] = (int) $fltr;
                            break;
                    }
                }
            }

            if (!empty($catIds)) {
                $filter['force'][] = ['column' => 'c.id', 'expr' => 'in', 'value' => $catIds];
            }
        }
        $orderBy    = $session->get('mautic.sms.orderby', 'e.name');
        $orderByDir = $session->get('mautic.sms.orderbydir', 'DESC');

        $smss = $model->getEntities([
            'start'      => $start,
            'limit'      => $limit,
            'filter'     => $filter,
            'orderBy'    => $orderBy,
            'orderByDir' => $orderByDir,
        ]);

        $count = count($smss);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if ($count === 1) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($count / $limit)) ?: 1;
            }

            $session->set('mautic.sms.page', $lastPage);
            $returnUrl = $this->generateUrl('le_sms_index', ['page' => $lastPage]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $lastPage],
                'contentTemplate' => 'MauticSmsBundle:Sms:index',
                'passthroughVars' => [
                    'activeLink'    => '#le_sms_index',
                    'leContent' => 'sms',
                ],
            ]);
        }
        $session->set('mautic.sms.page', $page);
        $isPublished       = $this->getSMSProviderStatus();
        $integration       = $this->get('mautic.helper.integration')->getIntegrationObject('SolutionInfinity');
        $smsBlockDetails   = $model->getSMSBlocks();

        return $this->delegateView([
            'viewParameters' => [
                'searchValue'      => $search,
                'items'            => $smss,
                'totalItems'       => $count,
                'page'             => $page,
                'limit'            => $limit,
                'tmpl'             => $this->request->get('tmpl', 'index'),
                'permissions'      => $permissions,
                'model'            => $model,
                'security'         => $this->get('mautic.security'),
                'configured'       => ($integration && $integration->getIntegrationSettings()->getIsPublished()),
                'smsBlockDetails'  => $smsBlockDetails,
                'filters'          => $listFilters,
                'isEnabled'        => $isPublished,
                ],
            'contentTemplate' => 'MauticSmsBundle:Sms:list.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_sms_index',
                'leContent' => 'sms',
                'route'         => $this->generateUrl('le_sms_index', ['page' => $page]),
            ],
        ]);
    }

    /**
     * Loads a specific form into the detailed panel.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        /** @var \Mautic\SmsBundle\Model\SmsModel $model */
        $model    = $this->getModel('sms');
        $security = $this->get('mautic.security');

        /** @var \Mautic\SmsBundle\Entity\Sms $sms */
        $sms = $model->getEntity($objectId);
        //set the page we came from
        $page = $this->get('session')->get('mautic.sms.page', 1);

        if ($sms === null) {
            //set the return URL
            $returnUrl = $this->generateUrl('le_sms_index', ['page' => $page]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'MauticSmsBundle:Sms:index',
                'passthroughVars' => [
                    'activeLink'    => '#le_sms_index',
                    'leContent' => 'sms',
                ],
                'flashes' => [
                    [
                        'type'    => 'error',
                        'msg'     => 'mautic.sms.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ],
                ],
            ]);
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'sms:smses:viewown',
            'sms:smses:viewother',
            $sms->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        // Audit Log
        $logs = $this->getModel('core.auditLog')->getLogForObject('sms', $sms->getId(), $sms->getDateAdded());

        // Init the date range filter form
        $dateRangeValues = $this->request->get('daterange', []);
        $action          = $this->generateUrl('le_sms_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeForm   = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $action]);
        $entityViews     = $model->getHitsLineChartData(
            null,
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData()),
            null,
            ['sms_id' => $sms->getId()]
        );

        $isPublished       = $this->getSMSProviderStatus();
        // Get click through stats
        $trackableLinks = $model->getSmsClickStats($sms->getId());

        return $this->delegateView([
            'returnUrl'      => $this->generateUrl('le_sms_action', ['objectAction' => 'view', 'objectId' => $sms->getId()]),
            'viewParameters' => [
                'sms'         => $sms,
                'trackables'  => $trackableLinks,
                'logs'        => $logs,
                'isEmbedded'  => $this->request->get('isEmbedded') ? $this->request->get('isEmbedded') : false,
                'permissions' => $security->isGranted([
                    'sms:smses:viewown',
                    'sms:smses:viewother',
                    'sms:smses:create',
                    'sms:smses:editown',
                    'sms:smses:editother',
                    'sms:smses:deleteown',
                    'sms:smses:deleteother',
                    'sms:smses:publishown',
                    'sms:smses:publishother',
                ], 'RETURN_ARRAY'),
                'security'    => $security,
                'actionRoute' => 'le_sms_action',
                'entityViews' => $entityViews,
                'contacts'    => $this->forward(
                    'MauticSmsBundle:Sms:contacts',
                    [
                        'objectId'   => $sms->getId(),
                        'page'       => $this->get('session')->get('mautic.sms.contact.page', 1),
                        'ignoreAjax' => true,
                    ]
                )->getContent(),
                'dateRangeForm' => $dateRangeForm->createView(),
                'isSmsEnabled'  => $isPublished,
            ],
            'contentTemplate' => 'MauticSmsBundle:Sms:details.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_sms_index',
                'leContent' => 'sms',
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param Sms $entity
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        /** @var \Mautic\SmsBundle\Model\SmsModel $model */
        $model = $this->getModel('sms');

        if (!$entity instanceof Sms) {
            /** @var \Mautic\SmsBundle\Entity\Sms $entity */
            $entity = $model->getEntity();
        }

        $method  = $this->request->getMethod();
        $session = $this->get('session');

        if (!$this->get('mautic.security')->isGranted('sms:smses:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page   = $session->get('mautic.sms.page', 1);
        $action = $this->generateUrl('le_sms_action', ['objectAction' => 'new']);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('sms[updateSelect]', false, true)
            : $this->request->get('updateSelect', false);

        if ($updateSelect) {
            $entity->setSmsType('template');
        }

        //create the form
        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if ($method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'le_sms_index',
                            '%url%'       => $this->generateUrl(
                                'le_sms_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $viewParameters = [
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId(),
                        ];
                        $returnUrl = $this->generateUrl('le_sms_action', $viewParameters);
                        $template  = 'MauticSmsBundle:Sms:view';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('le_sms_index', $viewParameters);
                $template       = 'MauticSmsBundle:Sms:index';
                //clear any modified content
                $session->remove('mautic.sms.'.$entity->getId().'.content');
            }

            $passthrough = [
                'activeLink'    => 'le_sms_index',
                'leContent' => 'sms',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $this->setFormTheme($form, 'MauticSmsBundle:Sms:form.html.php', 'MauticSmsBundle:FormTheme\Sms'),
                    'sms'  => $entity,
                ],
                'contentTemplate' => 'MauticSmsBundle:Sms:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#le_sms_index',
                    'leContent' => 'sms',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'le_sms_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     * @param bool $forceTypeSelection
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction($objectId, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \Mautic\SmsBundle\Model\SmsModel $model */
        $model   = $this->getModel('sms');
        $method  = $this->request->getMethod();
        $entity  = $model->getEntity($objectId);
        $session = $this->get('session');
        $page    = $session->get('mautic.sms.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('le_sms_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticSmsBundle:Sms:index',
            'passthroughVars' => [
                'activeLink'    => 'le_sms_index',
                'leContent' => 'sms',
            ],
        ];

        //not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.sms.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'sms:smses:viewown',
            'sms:smses:viewother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'sms');
        }

        //Create the form
        $action = $this->generateUrl('le_sms_action', ['objectAction' => 'edit', 'objectId' => $objectId]);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('sms[updateSelect]', false, true)
            : $this->request->get('updateSelect', false);

        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if (!$ignorePost && $method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'le_sms_index',
                            '%url%'       => $this->generateUrl(
                                'le_sms_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ],
                        'warning'
                    );
                }
            } else {
                //clear any modified content
                $session->remove('mautic.sms.'.$objectId.'.content');
                //unlock the entity
                $model->unlockEntity($entity);
            }

            $passthrough = [
                'activeLink'    => 'le_sms_index',
                'leContent' => 'sms',
            ];

            $template = 'MauticSmsBundle:Sms:view';

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $this->generateUrl('le_sms_action', $viewParameters),
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $template,
                            'passthroughVars' => $passthrough,
                        ]
                    )
                );
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'               => $this->setFormTheme($form, 'MauticSmsBundle:Sms:form.html.php', 'MauticSmsBundle:FormTheme\Sms'),
                    'sms'                => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                ],
                'contentTemplate' => 'MauticSmsBundle:Sms:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#le_sms_index',
                    'leContent' => 'sms',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'le_sms_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @param $objectId
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction($objectId)
    {
        $model  = $this->getModel('sms');
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->get('mautic.security')->isGranted('sms:smses:create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    'sms:smses:viewown',
                    'sms:smses:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param   $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->get('session')->get('mautic.sms.page', 1);
        $returnUrl = $this->generateUrl('le_sms_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticSmsBundle:Sms:index',
            'passthroughVars' => [
                'activeLink'    => 'le_sms_index',
                'leContent' => 'sms',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel('sms');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.sms.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'sms:smses:deleteown',
                'sms:smses:deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'sms');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->get('session')->get('mautic.sms.page', 1);
        $returnUrl = $this->generateUrl('le_sms_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticSmsBundle:Sms:index',
            'passthroughVars' => [
                'activeLink'    => '#le_sms_index',
                'leContent' => 'sms',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model = $this->getModel('sms');
            $ids   = json_decode($this->request->query->get('ids', '{}'));

            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.sms.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    'sms:smses:viewown',
                    'sms:smses:viewother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'sms', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.sms.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * @param $objectId
     *
     * @return JsonResponse|Response
     */
    public function previewAction($objectId)
    {
        /** @var \Mautic\SmsBundle\Model\SmsModel $model */
        $model    = $this->getModel('sms');
        $sms      = $model->getEntity($objectId);
        $security = $this->get('mautic.security');

        if ($sms !== null && $security->hasEntityAccess('sms:smses:viewown', 'sms:smses:viewother')) {
            return $this->delegateView([
                'viewParameters' => [
                    'sms' => $sms,
                ],
                'contentTemplate' => 'MauticSmsBundle:Sms:preview.html.php',
            ]);
        }

        return new Response('', Response::HTTP_NOT_FOUND);
    }

    /**
     * @param     $objectId
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction($objectId, $page = 1)
    {
        return $this->generateContactsGrid(
            $objectId,
            $page,
            'sms:smses:view',
            'sms',
            'sms_message_stats',
            'sms',
            'sms_id'
        );
    }

    /**
     * Generating the modal box content for
     * the send multiple example Sms option.
     */
    public function sendExampleAction($objectId)
    {
        $model  = $this->getModel('sms');
        $entity = $model->getEntity($objectId);
        if (!$this->get('mautic.helper.sms')->getSmsTransportStatus()){
            $this->addFlash('mautic.sms.notice.test_sent_multiple.fail');
            return $this->postActionRedirect(
                [
                    'passthroughVars' => [
                        'closeModal' => 1,
                        'route'      => false,
                    ],
                ]
            );
        }
        //not found or not allowed
        if ($entity === null
            || (!$this->get('mautic.security')->hasEntityAccess(
                'sms:smses:viewown',
                'sms:smses:viewother',
                $entity->getCreatedBy()
            ))
        ) {
            return $this->postActionRedirect(
                [
                    'passthroughVars' => [
                        'closeModal' => 1,
                        'route'      => false,
                    ],
                ]
            );
        }

        // Get the quick add form
        $action = $this->generateUrl('le_sms_action', ['objectAction' => 'sendExample', 'objectId' => $objectId]);
        $user   = $this->get('mautic.helper.user')->getUser();

        $form = $this->createForm(ExampleSendType::class, ['smss' => ['list' => [$user->getMobile()]]], ['action' => $action]);
        /* @var \Mautic\EmailBundle\Model\EmailModel $model */

        if ($this->request->getMethod() == 'POST') {
            $isCancelled = $this->isFormCancelled($form);
            $isValid     = $this->isFormValid($form);
            if (!$isCancelled && $isValid) {
                if($this->get('mautic.helper.sms')->getSmsTransportStatus()){
                    $mobiles = $form['smss']->getData()['list'];

                // Prepare a fake lead
                /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
                $fieldModel = $this->getModel('lead.field');
                $fields     = $fieldModel->getFieldList(false, false);
                array_walk(
                    $fields,
                    function (&$field) {
                        $field = "[$field]";
                    }
                );
                $fields['id'] = 0;

                $errors = [];
                foreach ($mobiles as $mobile) {
                    if (!empty($mobile)) {
                        $users = [
                            [
                                // Setting the id, firstname and lastname to null as this is a unknown user
                                'id'         => '',
                                'firstname'  => '',
                                'lastname'   => '',
                                'mobile'     => $mobile,
                            ],
                        ];

                        // Send to current user
                        $error = $model->sendSampleSmstoUser($entity, $users, $fields, [], [], false);
                        if (count($error)) {
                            array_push($errors, $error[0]);
                        }
                    }else{
                        $this->addFlash('mautic.sms.notice.test_sent.number.required');
                    }
                }

                if (count($errors) != 0) {
                    $this->addFlash('mautic.sms.notice.test_sent_multiple.fail');
                } else {
                        $this->addFlash('mautic.sms.notice.test_sent_multiple.success');
                }
                }else{
                    $this->addFlash('mautic.sms.notice.test_sent_multiple.fail');

                }
            }

            if ($isValid || $isCancelled) {
                return $this->postActionRedirect(
                    [
                        'passthroughVars' => [
                            'closeModal' => 1,
                            'route'      => false,
                        ],
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $form->createView(),
                ],
                'contentTemplate' => 'MauticSmsBundle:Sms:recipients.html.php',
            ]
        );
    }

    public function getSMSProviderStatus()
    {
        $transportChain  = $this->factory->get('mautic.sms.transport_chain');
        $transportHelper = $this->factory->get('mautic.helper.integration');
        $transports      = $transportChain->getEnabledTransports();
        $isEnabled       = false;

        foreach ($transports as $transportServiceId=>$transport) {
            $integration = $transportHelper->getIntegrationObject($this->translator->trans($transportServiceId));
            if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
                $isEnabled = true;
            }
        }

        return $isEnabled;
    }
}
