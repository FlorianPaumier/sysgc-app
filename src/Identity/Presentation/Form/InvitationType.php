<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Form;

use App\Identity\Domain\Enum\UserRole;
use App\Project\Domain\Model\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Adresse courriel', 'constraints' => [new Assert\NotBlank(), new Assert\Email()]])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => ['Administrateur' => UserRole::ADMIN, 'Contremaître' => UserRole::FOREMAN],
                'choice_value' => static fn (?UserRole $role): ?string => $role?->value,
            ])
            ->add('projects', EntityType::class, [
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => static fn (Project $project): string => $project->getName() ?? $project->getAddress() ?? 'Chantier',
                'multiple' => true,
                'required' => false,
                'label' => 'Chantiers assignés',
            ])
            ->add('send', SubmitType::class, ['label' => 'Inviter']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'organization_invitation', 'projects' => []]);
        $resolver->setAllowedTypes('projects', 'iterable');
    }
}
