<?php

declare(strict_types=1);

namespace App\Project\Presentation\Form;

use App\Project\Domain\Enum\ProjectStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formData = $options['data'] ?? null;
        $initialStatus = is_array($formData) && ($formData['status'] ?? null) instanceof ProjectStatus
            ? $formData['status']
            : ProjectStatus::ACTIVE;

        $builder
            ->add('address', TextType::class, [
                'label' => 'Adresse du chantier',
                'constraints' => [new NotBlank(message: 'Veuillez saisir l’adresse du chantier.')],
            ])
            ->add('clientName', TextType::class, [
                'label' => 'Nom du client',
                'constraints' => [new NotBlank(message: 'Veuillez saisir le nom du client.')],
            ])
            ->add('startDate', DateType::class, ['label' => 'Date de début', 'widget' => 'single_text'])
            ->add('endDate', DateType::class, ['label' => 'Date de fin', 'widget' => 'single_text', 'required' => false])
            ->add('budget', MoneyType::class, [
                'label' => 'Budget',
                'currency' => 'CAD',
                'input' => 'string',
                'constraints' => [new GreaterThanOrEqual(value: 0, message: 'Le budget doit être positif.')],
            ])
            ->add('status', EnumType::class, [
                'label' => 'État du chantier',
                'class' => ProjectStatus::class,
                'choices' => [ProjectStatus::ACTIVE, ProjectStatus::ON_HOLD, ProjectStatus::COMPLETED],
                'choice_label' => static fn (ProjectStatus $status): string => match ($status) {
                    ProjectStatus::ACTIVE => 'Actif',
                    ProjectStatus::ON_HOLD => 'En pause',
                    ProjectStatus::COMPLETED => 'Terminé',
                    ProjectStatus::ARCHIVED => 'Archivé',
                },
                'data' => $initialStatus,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_token_id' => 'project']);
    }
}
