<?php

declare(strict_types=1);

namespace App\Commercial\Presentation\Form;

use App\Commercial\Domain\Model\ChangeOrder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ChangeOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('costImpact', MoneyType::class, ['label' => 'Impact financier', 'currency' => 'CAD'])
            ->add('scheduleImpactDays', IntegerType::class, ['label' => 'Impact sur l’échéancier (jours)'])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer l’avenant']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ChangeOrder::class]);
    }
}
