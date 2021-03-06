<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Form\Type;

use DeviceDetector\Parser\Device\DeviceParserAbstract as DeviceParser;
use DeviceDetector\Parser\OperatingSystem;
use Mautic\AssetBundle\Model\AssetModel;
use Mautic\CategoryBundle\Model\CategoryModel;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Model\DripEmailModel;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\Model\FormModel;
use Mautic\LeadBundle\Form\DataTransformer\FieldFilterTransformer;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Model\ListOptInModel;
use Mautic\PageBundle\Model\PageModel;
use Mautic\StageBundle\Model\StageModel;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class ListType.
 */
class ListType extends AbstractType
{
    private $translator;
    private $fieldChoices        = [];
    private $timezoneChoices     = [];
    private $countryChoices      = [];
    private $regionChoices       = [];
    private $listChoices         = [];
    private $emailChoices        = [];
    private $deviceTypesChoices  = [];
    private $deviceBrandsChoices = [];
    private $deviceOsChoices     = [];
    private $tagChoices          = [];
    private $stageChoices        = [];
    private $localeChoices       = [];
    private $categoriesChoices   = [];
    private $landingpageChoices  = [];
    private $userchoices         = [];
    private $formSubmitChoices   = [];
    private $assetChoices        = [];
    private $scoreChoices        = [];
    private $dripEmailChoices    = [];
    private $dripEmailList       = [];
    private $listoptinChoices    = [];

    /**
     * ListType constructor.
     *
     * @param TranslatorInterface $translator
     * @param ListModel           $listModel
     * @param EmailModel          $emailModel
     * @param CorePermissions     $security
     * @param LeadModel           $leadModel
     * @param StageModel          $stageModel
     * @param CategoryModel       $categoryModel
     * @param UserHelper          $userHelper
     * @param PageModel           $pageModel
     * @param UserModel           $userModel
     * @param FormModel           $formModel
     * @param AssetModel          $assetModel
     * @param DripEmailModel      $dripEmailModel
     * @param ListOptInModel      $listOptInModel
     */
    public function __construct(TranslatorInterface $translator, ListModel $listModel, EmailModel $emailModel, CorePermissions $security, LeadModel $leadModel, StageModel $stageModel, CategoryModel $categoryModel, UserHelper $userHelper, PageModel $pageModel, UserModel $userModel, FormModel $formModel, AssetModel $assetModel, DripEmailModel $dripEmailModel, ListOptInModel $listOptInModel)
    {
        $this->translator = $translator;

        $this->fieldChoices = $listModel->getChoiceFields();

        // Locales
        $this->timezoneChoices = FormFieldHelper::getCustomTimezones();
        $this->countryChoices  = FormFieldHelper::getCountryChoices();
        $this->regionChoices   = FormFieldHelper::getRegionChoices();
        $this->localeChoices   = FormFieldHelper::getLocaleChoices();

        $this->scoreChoices   =['hot'=>'Hot', 'warm'=>'Warm', 'cold'=>'Cold'];
        // Segments
        $lists = $listModel->getUserLists();
        foreach ($lists as $list) {
            $this->listChoices[$list['id']] = $list['name'];
        }

        // Lists
        $listoptins = $listOptInModel->getListsOptIn();
        foreach ($listoptins as $listoptin) {
            $this->listoptinChoices[$listoptin['id']] = $listoptin['name'];
        }

        $viewOther   = $security->isGranted('email:emails:viewother');
        $currentUser = $userHelper->getUser();
        $emailRepo   = $emailModel->getRepository();

        $emailRepo->setCurrentUser($currentUser);

        $emails = $emailRepo->getEmailList('', 0, 0, $viewOther, true, 'list');

        foreach ($emails as $email) {
            $this->emailChoices[$email['language']][$email['id']] = $email['name'];
        }
        ksort($this->emailChoices);

        $viewOther   = $security->isGranted('form:forms:viewother');
        $formRepo    = $formModel->getRepository();
        $formRepo->setCurrentUser($currentUser);

        $forms = $formRepo->getFormList('', 0, 0, false, $viewOther);

        foreach ($forms as $form) {
            $this->formSubmitChoices[$form['id']] = $form['name'];
        }
        ksort($this->formSubmitChoices);

        $viewOther   = $security->isGranted('asset:assets:viewother');
        $assetRepo   = $assetModel->getRepository();
        $assetRepo->setCurrentUser($currentUser);

        $assets = $assetRepo->getAssetList('', 0, 0, true, $viewOther);

        foreach ($assets as $asset) {
            $this->assetChoices[$asset['language']][$asset['id']] = $asset['title'];
        }
        ksort($this->assetChoices);

        $isadmin    =$userModel->getCurrentUserEntity()->isAdmin();
        $filterarray= [
            'force' => [
                [
                    'column' => 'u.isPublished',
                    'expr'   => 'eq',
                    'value'  => true,
                ],
                [
                    'column' => 'u.id',
                    'expr'   => 'neq',
                    'value'  => '1',
                ],
            ],
        ];
        if ($isadmin) {
            $filterarray= [
                'force' => [
                    [
                        'column' => 'u.isPublished',
                        'expr'   => 'eq',
                        'value'  => true,
                    ],
                ],
            ];
        }
        $choices = $userModel->getRepository()->getEntities(
            [
                'filter' => $filterarray,
            ]
        );

        foreach ($choices as $choice) {
            $this->userchoices[$choice->getId()] = $choice->getName(true);
        }

        //sort by language
        ksort($this->userchoices);

        $pageRepo   = $pageModel->getRepository();

        $pageList = $pageRepo->getPageList('', 0, 0, $viewOther, true);

        foreach ($pageList as $page) {
            $this->landingpageChoices[$page['language']][$page['id']] = $page['title'];
        }
        ksort($this->landingpageChoices);

        $tags = $leadModel->getTagList();
        foreach ($tags as $tag) {
            $this->tagChoices[$tag['value']] = $tag['label'];
        }

        $stages = $stageModel->getRepository()->getSimpleList();
        foreach ($stages as $stage) {
            $this->stageChoices[$stage['value']] = $stage['label'];
        }

        $categories = $categoryModel->getLookupResults('global');

        foreach ($categories as $category) {
            $this->categoriesChoices[$category['id']] = $category['title'];
        }

        $driEmailRepo=$dripEmailModel->getRepository();
        $driEmailRepo->setCurrentUser($currentUser);

        $dripEmails = $driEmailRepo->getAllDripEmailList();

        foreach ($dripEmails as $dripEmail) {
            $this->dripEmailChoices[$dripEmail['dripname']][$dripEmail['id']] = $dripEmail['name'];
        }
        ksort($this->dripEmailChoices);

        $dripEmailsLists = $driEmailRepo->getDripEmailList();

        foreach ($dripEmailsLists as $dripEmails) {
            $this->dripEmailList[$dripEmails['id']] = $dripEmails['name'];
        }
        ksort($this->dripEmailList);

        $this->deviceTypesChoices  = array_combine((DeviceParser::getAvailableDeviceTypeNames()), (DeviceParser::getAvailableDeviceTypeNames()));
        $this->deviceBrandsChoices = DeviceParser::$deviceBrands;
        $this->deviceOsChoices     = array_combine((array_keys(OperatingSystem::getAvailableOperatingSystemFamilies())), array_keys(OperatingSystem::getAvailableOperatingSystemFamilies()));
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(['description' => 'html']));
        $builder->addEventSubscriber(new FormExitSubscriber('lead.list', $options));

        $builder->add(
            'name',
            'text',
            [
                'label'      => 'mautic.core.name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control le-input'],
            ]
        );

        $builder->add(
            'alias',
            'text',
            [
                'label'      => 'mautic.core.alias',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control le-input',
                    'length'  => 25,
                    'tooltip' => 'le.lead.list.help.alias',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'description',
            'textarea',
            [
                'label'      => 'mautic.core.description',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control editor'],
                'required'   => false,
            ]
        );

        $builder->add(
            'isGlobal',
            'yesno_button_group',
            [
                'label' => 'le.lead.list.form.isglobal',
            ]
        );

        $builder->add('isPublished', 'yesno_button_group', [
            'no_label'   => 'mautic.core.form.unpublished',
            'yes_label'  => 'mautic.core.form.published',
            ]);

        $filterModalTransformer = new FieldFilterTransformer($this->translator);
        $builder->add(
            $builder->create(
                'filters',
                'collection',
                [
                    'type'    => 'leadlist_filter',
                    'options' => [
                        'label'                => false,
                        'timezones'            => $this->timezoneChoices,
                        'countries'            => $this->countryChoices,
                        'regions'              => $this->regionChoices,
                        'fields'               => $this->fieldChoices,
                        'lists'                => $this->listChoices,
                        'emails'               => $this->emailChoices,
                        'deviceTypes'          => $this->deviceTypesChoices,
                        'deviceBrands'         => $this->deviceBrandsChoices,
                        'deviceOs'             => $this->deviceOsChoices,
                        'tags'                 => $this->tagChoices,
                        'stage'                => $this->stageChoices,
                        'locales'              => $this->localeChoices,
                        'globalcategory'       => $this->categoriesChoices,
                        'users'                => $this->userchoices,
                        'landingpage_list'     => $this->landingpageChoices,
                        'score_list'           => $this->scoreChoices,
                        'formsubmit_list'      => $this->formSubmitChoices,
                        'asset_downloads_list' => $this->assetChoices,
                        'drip_email_received'  => $this->dripEmailChoices,
                        'drip_email_list'      => $this->dripEmailList,
                        'listoptin'            => $this->listoptinChoices,
                    ],
                    'error_bubbling' => false,
                    'mapped'         => true,
                    'allow_add'      => true,
                    'allow_delete'   => true,
                    'label'          => false,
                ]
            )->addModelTransformer($filterModalTransformer)
        );

        $builder->add('buttons', 'form_buttons',
            [
                'apply_icon'   => false,
                'save_icon'    => false,
                'apply_text'   => false,
            ]
        );

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Mautic\LeadBundle\Entity\LeadList',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['fields']            = $this->fieldChoices;
        $view->vars['countries']         = $this->countryChoices;
        $view->vars['regions']           = $this->regionChoices;
        $view->vars['timezones']         = $this->timezoneChoices;
        $view->vars['lists']             = $this->listChoices;
        $view->vars['emails']            = $this->emailChoices;
        $view->vars['deviceTypes']       = $this->deviceTypesChoices;
        $view->vars['deviceBrands']      = $this->deviceBrandsChoices;
        $view->vars['deviceOs']          = $this->deviceOsChoices;
        $view->vars['tags']              = $this->tagChoices;
        $view->vars['stage']             = $this->stageChoices;
        $view->vars['locales']           = $this->localeChoices;
        $view->vars['globalcategory']    = $this->categoriesChoices;
        $view->vars['landingpage_list']  = $this->landingpageChoices;
        $view->vars['score_list']        = $this->scoreChoices;
        $view->vars['users']             = $this->userchoices;
        $view->vars['forms']             = $this->formSubmitChoices;
        $view->vars['assets']            = $this->assetChoices;
        $view->vars['drip_campaign']     = $this->dripEmailChoices;
        $view->vars['drip_campaign_list']= $this->dripEmailList;
        $view->vars['listoptin']         = $this->listoptinChoices;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'leadlist';
    }
}
