<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PointBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\PointBundle\Entity\Point;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PointController.
 */
class PointController extends AbstractFormController
{
    /**
     * @param int $page
     *
     * @return JsonResponse|Response
     */
    public function indexAction($page = 1)
    {
        if ($redirectUrl=$this->get('le.helper.statemachine')->checkStateAndRedirectPage()) {
            return $this->delegateRedirect($redirectUrl);
        }
        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted([
            'point:points:view',
            'point:points:create',
            'point:points:edit',
            'point:points:delete',
            'point:points:publish',
        ], 'RETURN_ARRAY');

        if (!$permissions['point:points:view']) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $listFilters = [
            'filters' => [
                'placeholder' => $this->get('translator')->trans('le.category.filter.placeholder'),
                'multiple'    => true,
            ],
        ];
        // Reset available groups
        $listFilters['filters']['groups'] = [];

        //set limits
        $limit = $this->get('session')->get('mautic.point.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $this->get('session')->get('mautic.point.filter', ''));
        $this->get('session')->set('mautic.point.filter', $search);

        $filter     = ['string' => $search, 'force' => []];

        $listFilters['filters']['groups']['mautic.core.filter.category'] = [
            'options' => $this->getModel('category.category')->getLookupResults('point'),
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
                $categorymodel   = $this->getModel('category');
                $category_search = '';
                for ($lid = 0; $lid < sizeof($catIds); ++$lid) {
                    $categorylist = $categorymodel->getEntity($catIds[$lid]);
                    $values[]     = $catIds[$lid];
                    if ($lid == 0 && $filter['string'] == '') {
                        $category_search = 'category:';
                    } else {
                        $category_search .= ' or category:';
                    }
                    if ($categorylist != null) {
                        $category_search .= $categorylist->getAlias();
                    }
                }
                $filter['string'] .= $category_search;
            }
            if (!empty($catIds)) {
                // $filter['force'][] = ['column' => 'c.id', 'expr' => 'in', 'value' => $catIds];
            }
        }
        $orderBy    = $this->get('session')->get('mautic.point.orderby', 'p.name');
        $orderByDir = $this->get('session')->get('mautic.point.orderbydir', 'ASC');

        $points = $this->getModel('point')->getEntities([
            'start'      => $start,
            'limit'      => $limit,
            'filter'     => $filter,
            'orderBy'    => $orderBy,
            'orderByDir' => $orderByDir,
        ]);

        $count = count($points);
        if ($count && $count < ($start + 1)) {
            $lastPage = ($count === 1) ? 1 : (ceil($count / $limit)) ?: 1;
            $this->get('session')->set('mautic.point.page', $lastPage);
            $returnUrl = $this->generateUrl('le_point_index', ['page' => $lastPage]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $lastPage],
                'contentTemplate' => 'MauticPointBundle:Point:index',
                'passthroughVars' => [
                    'activeLink'    => '#le_point_index',
                    'leContent'     => 'point',
                ],
            ]);
        }

        //set what page currently on so that we can return here after form submission/cancellation
        $this->get('session')->set('mautic.point.page', $page);

        $tmpl = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';

        //get the list of actions
        $actions            = $this->getModel('point')->getPointActions();
        $pointsBlockDetails = $this->getModel('point')->getPointsBlocks();

        return $this->delegateView([
            'viewParameters' => [
                'searchValue'        => $search,
                'filters'            => $listFilters,
                'items'              => $points,
                'actions'            => $actions['actions'],
                'page'               => $page,
                'limit'              => $limit,
                'permissions'        => $permissions,
                'tmpl'               => $tmpl,
                'pointsBlockDetails' => $pointsBlockDetails,
            ],
            'contentTemplate' => 'MauticPointBundle:Point:list.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
                'route'         => $this->generateUrl('le_point_index', ['page' => $page]),
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param \Mautic\PointBundle\Entity\Point $entity
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function newAction($entity = null)
    {
        if ($redirectUrl=$this->get('le.helper.statemachine')->checkStateAndRedirectPage()) {
            return $this->delegateRedirect($redirectUrl);
        }
        $model = $this->getModel('point');

        if (!($entity instanceof Point)) {
            /** @var \Mautic\PointBundle\Entity\Point $entity */
            $entity = $model->getEntity();
        }

        if (!$this->get('mautic.security')->isGranted('point:points:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page = $this->get('session')->get('mautic.point.page', 1);

        $actionType = ($this->request->getMethod() == 'POST') ? $this->request->request->get('point[type]', '', true) : '';

        $action  = $this->generateUrl('le_point_action', ['objectAction' => 'new']);
        $actions = $model->getPointActions();
        $form    = $model->createForm($entity, $this->get('form.factory'), $action, [
            'pointActions' => $actions,
            'actionType'   => $actionType,
        ]);
        $viewParameters = ['page' => $page];

        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash('mautic.core.notice.created', [
                        '%name%'      => $entity->getName(),
                        '%menu_link%' => 'le_point_index',
                        '%url%'       => $this->generateUrl('le_point_action', [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]),
                    ]);

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $returnUrl = $this->generateUrl('le_point_index', $viewParameters);
                        $template  = 'MauticPointBundle:Point:index';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $returnUrl = $this->generateUrl('le_point_index', $viewParameters);
                $template  = 'MauticPointBundle:Point:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect([
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => $viewParameters,
                    'contentTemplate' => $template,
                    'passthroughVars' => [
                        'activeLink'    => 'le_point_index',
                        'leContent'     => 'point',
                    ],
                ]);
            }
        }

        $themes = ['MauticPointBundle:FormTheme\Action'];
        if ($actionType && !empty($actions['actions'][$actionType]['formTheme'])) {
            $themes[] = $actions['actions'][$actionType]['formTheme'];
        }

        return $this->delegateView([
            'viewParameters' => [
                //'tmpl'    => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'entity'  => $entity,
                'form'    => $this->setFormTheme($form, 'MauticPointBundle:Point:form.html.php', $themes),
                'actions' => $actions['actions'],
            ],
            'contentTemplate' => 'MauticPointBundle:Point:form.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
                'route'         => $this->generateUrl('le_point_action', [
                        'objectAction' => (!empty($valid) ? 'edit' : 'new'), //valid means a new form was applied
                        'objectId'     => $entity->getId(),
                    ]
                ),
            ],
        ]);
    }

    /**
     * Generates edit form and processes post data.
     *
     * @param int  $objectId
     * @param bool $ignorePost
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction($objectId, $ignorePost = false)
    {
        if ($redirectUrl=$this->get('le.helper.statemachine')->checkStateAndRedirectPage()) {
            return $this->delegateRedirect($redirectUrl);
        }
        $model  = $this->getModel('point');
        $entity = $model->getEntity($objectId);

        //set the page we came from
        $page = $this->get('session')->get('mautic.point.page', 1);

        $viewParameters = ['page' => $page];

        //set the return URL
        $returnUrl = $this->generateUrl('le_point_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => $viewParameters,
            'contentTemplate' => 'MauticPointBundle:Point:index',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
            ],
        ];

        //form not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge($postActionVars, [
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.point.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ])
            );
        } elseif (!$this->get('mautic.security')->isGranted('point:points:edit')) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'point');
        }

        $actionType = ($this->request->getMethod() == 'POST') ? $this->request->request->get('point[type]', '', true) : $entity->getType();

        $action  = $this->generateUrl('le_point_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $actions = $model->getPointActions();
        $form    = $model->createForm($entity, $this->get('form.factory'), $action, [
            'pointActions' => $actions,
            'actionType'   => $actionType,
        ]);

        ///Check for a submitted form and process it
        if (!$ignorePost && $this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash('mautic.core.notice.updated', [
                        '%name%'      => $entity->getName(),
                        '%menu_link%' => 'le_point_index',
                        '%url%'       => $this->generateUrl('le_point_action', [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]),
                    ]);

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $returnUrl = $this->generateUrl('le_point_index', $viewParameters);
                        $template  = 'MauticPointBundle:Point:index';
                    }
                }
            } else {
                //unlock the entity
                $model->unlockEntity($entity);

                $returnUrl = $this->generateUrl('le_point_index', $viewParameters);
                $template  = 'MauticPointBundle:Point:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    array_merge($postActionVars, [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                    ])
                );
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);
        }

        $themes = ['MauticPointBundle:FormTheme\Action'];
        if (!empty($actions['actions'][$actionType]['formTheme'])) {
            $themes[] = $actions['actions'][$actionType]['formTheme'];
        }

        return $this->delegateView([
            'viewParameters' => [
                //'tmpl'    => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'entity'  => $entity,
                'form'    => $this->setFormTheme($form, 'MauticPointBundle:Point:form.html.php', $themes),
                'actions' => $actions['actions'],
            ],
            'contentTemplate' => 'MauticPointBundle:Point:form.html.php',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
                'route'         => $this->generateUrl('le_point_action', [
                        'objectAction' => 'edit',
                        'objectId'     => $entity->getId(),
                    ]
                ),
            ],
        ]);
    }

    /**
     * Clone an entity.
     *
     * @param int $objectId
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction($objectId)
    {
        $model  = $this->getModel('point');
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->get('mautic.security')->isGranted('point:points:create')) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
            $entity->setIsPublished(false);
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param int $objectId
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->get('session')->get('mautic.point.page', 1);
        $returnUrl = $this->generateUrl('le_point_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPointBundle:Point:index',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel('point');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.point.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->isGranted('point:points:delete')) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'point');
            }

            $model->deleteEntity($entity);

            $identifier = $this->get('translator')->trans($entity->getName());
            $flashes[]  = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $identifier,
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, [
                'flashes' => $flashes,
            ])
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->get('session')->get('mautic.point.page', 1);
        $returnUrl = $this->generateUrl('le_point_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPointBundle:Point:index',
            'passthroughVars' => [
                'activeLink'    => '#le_point_index',
                'leContent'     => 'point',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model     = $this->getModel('point');
            $ids       = json_decode($this->request->query->get('ids', '{}'));
            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.point.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->isGranted('point:points:delete')) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'point', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.point.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, [
                'flashes' => $flashes,
            ])
        );
    }
}
