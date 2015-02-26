<?php

namespace PROCERGS\LoginCidadao\CoreBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class TwoFactorAuthenticationDisableFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('current_password', 'password',
                  array(
                'label' => 'If you want to proceed, type your account\'s password to confirm:',
                'required' => true,
                'constraints' => new UserPassword(),
                'mapped' => false
            ))
            ->add('disable', 'submit',
                  array(
                'attr' => array('class' => 'btn btn-danger'),
                'label' => 'I understand the risks. Disable Two-Factor Authentication')
        );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'PROCERGS\LoginCidadao\CoreBundle\Entity\Person'
        ));
    }

    public function getName()
    {
        return 'lc_disable_2fa';
    }

}
