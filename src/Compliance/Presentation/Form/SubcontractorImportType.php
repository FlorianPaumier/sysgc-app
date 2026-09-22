<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

final class SubcontractorImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('csv', FileType::class, ['label' => 'Fichier CSV', 'required' => true, 'mapped' => false]);
    }
}
