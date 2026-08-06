<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddressType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, [
                'label' => 'Rue',
                'empty_data' => '',
            ])
            ->add('street2', TextType::class, [
                'label' => 'Appartement, suite, etc.',
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'empty_data' => '',
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'empty_data' => '',
            ])
            ->add('state', TextType::class, [
                'label' => 'Province / État',
                'required' => false,
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'Pays',
                'choices' => [
                    'Canada' => 'CA',
                    'France' => 'FR',
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
