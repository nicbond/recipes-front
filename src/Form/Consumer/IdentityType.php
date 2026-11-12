<?php

namespace App\Form\Consumer;

use App\Model\User\Identity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class IdentityType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Identity> $builder
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gender', ChoiceType::class, [
                'label' => 'form.user.gender',
                'translation_domain' => 'form',
                'placeholder' => 'Veuillez sélectionner votre civilité',
                'choices' => [
                    'Monsieur' => 'M',
                    'Madame' => 'F',
                ],
                'constraints' => [
                    new NotBlank(message: 'form.gender.required'),
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'form.user.firstname',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'given-name',
                    'maxlength' => 25,
                ],
                'constraints' => [
                    new NotBlank(message: 'form.firstname.required'),
                    new Length(
                        max: 25,
                        maxMessage: 'form.firstname.max_length',
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Z-ëêéèâàäôïîç ]+$/',
                        message: 'form.firstname.invalid',
                    ),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'form.user.lastname',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'family-name',
                    'maxlength' => 25,
                ],
                'constraints' => [
                    new NotBlank(message: 'form.lastname.required'),
                    new Length(
                        max: 25,
                        maxMessage: 'form.lastname.max_length',
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Z-ëêéèâàäôïîç ]+$/',
                        message: 'form.lastname.invalid',
                    ),
                ],
            ])
            ->add('birthdate', DateType::class, [
                'label' => 'form.user.birthdate',
                'translation_domain' => 'form',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'form.birthdate.required'),
                    new GreaterThan(
                        value: new \DateTimeImmutable('1900-01-01'),
                        message: 'form.birthdate.invalid',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Identity::class]);
    }
}
