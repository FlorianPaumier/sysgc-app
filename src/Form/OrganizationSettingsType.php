<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class OrganizationSettingsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le nom de l\'entreprise.'),
                    new Length(max: 255, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('rbqLicense', TextType::class, [
                'label' => 'Numéro de licence RBQ',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'RBQ-1234-5678-90'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre numéro de licence RBQ.'),
                    new Regex(
                        pattern: '/^RBQ-\d{4}-\d{4}-\d{2}$/',
                        message: 'Le numéro RBQ doit respecter le format RBQ-1234-5678-90.',
                    ),
                ],
            ])
            ->add('address', AddressType::class, [
                'label' => 'Adresse de facturation',
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'organization_settings',
        ]);
    }
}
