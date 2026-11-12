<?php

namespace App\Form\Consumer;

use App\Model\User\RegistrationConsumer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Valid;

class RegistrationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<RegistrationConsumer> $builder
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.user.email',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'Veuillez saisir votre adresse email',
                ],
                'constraints' => [
                    new NotBlank(message: 'form.email.required'),
                    new \Symfony\Component\Validator\Constraints\Email(
                        message: 'form.email.invalid',
                    ),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'form.password_confirmation.mismatch',
                'first_options' => [
                    'label' => 'form.user.password',
                    'translation_domain' => 'form',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-password-visibility-target' => 'input',
                    ],
                ],
                'second_options' => [
                    'label' => 'form.user.password_confirmation',
                    'translation_domain' => 'form',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-password-visibility-target' => 'input',
                    ],
                ],
                'constraints' => [
                    new NotBlank(message: 'form.password.required'),
                    new Length(
                        min: 12,
                        minMessage: 'form.password.length_min',
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&^#])[A-Za-z\d@$!%*?&^#]{12,}$/',
                        message: 'form.password.valid',
                    ),
                ],
            ])
            ->add('identity', IdentityType::class, [
                'label' => false,
                'constraints' => [
                    new Valid(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationConsumer::class,
            'csrf_protection' => true,
        ]);
    }
}
