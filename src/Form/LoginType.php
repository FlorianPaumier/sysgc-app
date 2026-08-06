<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Passwordless login form — email + CSRF only.
 */
final class LoginType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Courriel',
            'attr' => [
                'autocomplete' => 'email',
                'autofocus' => true,
            ],
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir votre courriel.'),
            ],
        ])
            ->add('send', SubmitType::class, [
                'label' => 'Recevoir mon lien de connexion',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Not bound to an entity; CSRF protection enabled by default.
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'authenticate',
        ]);
    }
}
