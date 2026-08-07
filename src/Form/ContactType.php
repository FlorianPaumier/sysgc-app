<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ContactDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class)
            ->add('subject', ChoiceType::class, [
                'choices' => [
                    'Question sur les forfaits' => 'Question sur les forfaits',
                    'Support technique' => 'Support technique',
                    'Conformité CCQ' => 'Conformité CCQ',
                    'Vérification RBQ' => 'Vérification RBQ',
                    'Facturation' => 'Facturation',
                    'Autre' => 'Autre',
                ],
                'placeholder' => 'Choisissez un sujet',
            ])
            ->add('email', EmailType::class)
            ->add('message', TextareaType::class)
            ->add('website', TextareaType::class, [
                'required' => false,
                'mapped' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactDTO::class,
        ]);
    }
}
