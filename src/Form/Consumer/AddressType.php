<?php

namespace App\Form\Consumer;

use App\Model\User\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Country;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AddressType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Address> $builder
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, [
                'label' => 'form.user.street',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'address-line1',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new NotBlank(message: 'form.street.required'),
                    new Length(
                        max: 50,
                        maxMessage: 'form.street.max_length',
                    ),
                ],
            ])
            ->add('complement', TextType::class, [
                'label' => 'form.user.complement',
                'translation_domain' => 'form',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'autocomplete' => 'address-line2',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new Length(
                        max: 50,
                        maxMessage: 'form.complement.max_length',
                    ),
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'form.user.postal_code',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'postal-code',
                    'inputmode' => 'numeric',
                    'maxlength' => 5,
                ],
                'constraints' => [
                    new NotBlank(message: 'form.postal_code.required'),
                    new Regex(
                        pattern: '/^\d{5}$/',
                        message: 'form.postal_code.invalid',
                    ),
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'form.user.city',
                'translation_domain' => 'form',
                'attr' => [
                    'autocomplete' => 'address-level2',
                    'maxlength' => 40,
                ],
                'constraints' => [
                    new NotBlank(message: 'form.city.required'),
                    new Length(
                        max: 40,
                        maxMessage: 'form.city.max_length',
                    ),
                ],
            ])
            ->add('countryCode', CountryType::class, [
                'label' => 'form.user.country',
                'translation_domain' => 'form',
                'choice_translation_locale' => 'fr',
                'preferred_choices' => ['FR'],
                'constraints' => [
                    new NotBlank(message: 'form.country.required'),
                    new Country(message: 'form.country.invalid'),
                ],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'form.user.phone_number',
                'translation_domain' => 'form',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                    'maxlength' => 10,
                ],
                'constraints' => [
                    new Length(
                        max: 10,
                        maxMessage: 'form.phone_number.max_length',
                    ),
                    new Regex(
                        pattern: '/^0[1-9](?:\d{2}){4}$/',
                        message: 'form.phone_number.invalid',
                    ),
                ],
            ])
            ->add('extraInformation', TextareaType::class, [
                'label' => 'form.user.extra_information',
                'translation_domain' => 'form',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new Length(
                        max: 255,
                        maxMessage: 'form.extra_information.max_length',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
