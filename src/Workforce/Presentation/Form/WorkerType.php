<?php

declare(strict_types=1);

namespace App\Workforce\Presentation\Form;

use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use App\Workforce\Domain\Model\Worker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WorkerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('ccqTrade', EnumType::class, [
                'class' => CcqTrade::class,
                'choice_label' => static fn (CcqTrade $trade): string => $trade->label(),
                'label' => 'Métier CCQ',
            ])
            ->add('ccqRegion', EnumType::class, [
                'class' => CcqRegion::class,
                'choice_label' => static fn (CcqRegion $region): string => $region->label(),
                'label' => 'Région CCQ',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Worker::class,
        ]);
    }
}
