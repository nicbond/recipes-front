<?php

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.user.email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'Veuillez saisir votre adresse email',
                ],
                'translation_domain' => 'form',
                'constraints' => [
                    new NotBlank(message: 'form.email.required'),
                    new Email(message: 'form.email.invalid'),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.user.password',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'current-password',
                    'data-password-visibility-target' => 'input',
                ],
                'constraints' => [
                    new NotBlank(message: 'form.password.required'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
