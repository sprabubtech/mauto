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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class FormSubmitActionAddUtmTagType.
 */
class AddToCompanyActionType extends AbstractType
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'company',
            'company_list',
            [
                'multiple'    => false,
                'required'    => true,
                'modal_route' => false,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'le.company.choosecompany.notblank']
                    ),
                ],
            ]
        );

        $windowUrl = $this->router->generate(
            'le_company_action',
            [
                'objectAction' => 'new',
                'contentOnly'  => 1,
                'updateSelect' => 'campaignevent_properties_company',
            ]
        );

        $builder->add(
            'newCompanyButton',
            'button',
            [
                'attr' => [
                    'class'   => 'btn btn-primary btn-nospin',
                    'onclick' => 'Le.loadNewWindow({"windowUrl": "'.$windowUrl.'"})',
                    'icon'    => 'fa fa-plus',
                ],
                'label' => 'le.company.new.company',
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'addtocompany_action';
    }
}
