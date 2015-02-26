<?php

namespace PROCERGS\LoginCidadao\CoreBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class TwoFactorAuthenticationBackupCodeGenerationFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('current_password', 'password',
                  array(
                'label' => 'To prevent unauthorized Backup Code generation type your password to confirm:',
                'required' => true,
                'constraints' => new UserPassword(),
                'mapped' => false
            ))
            ->add('generate', 'submit',
                  array(
                'attr' => array('class' => 'btn btn-success'),
                'label' => 'Generate new Backup Codes')
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
        return 'lc_generate_2fa_backup_codes';
    }

}
