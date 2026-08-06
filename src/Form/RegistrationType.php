<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Passwordless registration form: email + organization name + RBQ license.
 * No password. Not bound to an entity; the controller reads the raw values.
 */
final class RegistrationType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Courriel',
            'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir votre courriel.'),
                new Email(message: 'Le courriel « {{ value }} » n\'est pas valide.'),
            ],
        ])
            ->add('organizationName', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'attr' => ['autocomplete' => 'organization'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le nom de l\'entreprise.'),
                    new Length(max: 255, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('rbqLicense', TextType::class, [
                'label' => 'Numéro de licence RBQ',
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'RBQ-1234-5678-90'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre numéro de licence RBQ.'),
                    new Regex(
                        pattern: '/^RBQ-\d{4}-\d{4}-\d{2}$/',
                        message: 'Le numéro RBQ doit respecter le format RBQ-1234-5678-90.',
                    ),
                ],
            ])
            ->add('register', SubmitType::class, [
                'label' => 'Créer mon compte',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration',
        ]);
    }
}
