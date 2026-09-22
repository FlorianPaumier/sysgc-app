<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Form;

use App\Compliance\Domain\Model\Subcontractor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class SubcontractorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de l’entreprise'])
            ->add('rbqLicenseNumber', TextType::class, ['label' => 'Numéro de licence RBQ', 'required' => false])
            ->add('category', TextType::class, ['label' => 'Catégorie', 'required' => false])
            ->add('expiryDate', DateType::class, ['label' => 'Date d’expiration', 'required' => false, 'widget' => 'single_text'])
            ->add('manuallyVerified', CheckboxType::class, ['label' => 'Consigner une vérification manuelle', 'required' => false, 'mapped' => false])
            ->add('manualVerificationSource', TextType::class, [
                'label' => 'Source de la vérification',
                'help' => 'Ex. registre RBQ, confirmation téléphonique ou document officiel.',
                'required' => false,
                'mapped' => false,
            ])
            ->add('manualVerificationNotes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'mapped' => false,
            ])
            ->add('manualVerificationEvidence', FileType::class, [
                'label' => 'Preuve (PDF ou image)',
                'help' => 'Maximum 5 Mo.',
                'required' => false,
                'mapped' => false,
                'constraints' => [new File(
                    maxSize: '5M',
                    mimeTypes: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
                    mimeTypesMessage: 'La preuve doit être un PDF ou une image JPEG, PNG ou WEBP.',
                )],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Subcontractor::class]);
    }
}
