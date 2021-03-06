<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class FormSubmitActionUserEmailType.
 */
class FormSubmitActionUserEmailType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('useremail', 'emailsend_list', [
            'label' => 'le.email.emails',
            'attr'  => [
                'class'   => 'form-control le-input',
                'tooltip' => 'le.email.choose.emails_descr',
            ],
            'update_select'       => 'formaction_properties_useremail_email',
            'set_email_list_type' => 'template',
            'with_email_types'    => 'true',
        ]);

        $builder->add('user_id', 'user_list', [
            'label'      => 'mautic.email.form.users',
            'label_attr' => ['class' => 'control-label'],
            'required'   => true,
            'attr'       => [
                'class'   => 'form-control le-input',
                'tooltip' => 'mautic.core.help.autocomplete',
            ],
            'constraints' => [
                new NotBlank(['message' => 'mautic.core.value.required']),
            ],
        ]);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'email_submitaction_useremail';
    }
}
