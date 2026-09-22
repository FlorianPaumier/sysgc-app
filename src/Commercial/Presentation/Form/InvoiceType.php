<?php

declare(strict_types=1);

namespace App\Commercial\Presentation\Form;

use App\Commercial\Domain\Model\Invoice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

final class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('milestoneName', TextType::class, ['label' => 'Jalon facturé'])
            ->add('percentage', IntegerType::class, [
                'label' => 'Pourcentage du chantier',
                'attr' => ['min' => 1, 'max' => 100, 'data-invoice-milestone-target' => 'percentage'],
                'constraints' => [new GreaterThanOrEqual(1, message: 'Le pourcentage doit être au moins 1 %.'), new LessThanOrEqual(100, message: 'Le pourcentage ne peut pas dépasser 100 %.')],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant hors taxes',
                'currency' => 'CAD',
                'attr' => ['data-invoice-milestone-target' => 'amount'],
                'constraints' => [new GreaterThanOrEqual(0, message: 'Le montant doit être positif.')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Créer la facture']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Invoice::class]);
    }
}
