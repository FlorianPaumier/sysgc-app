<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Compliance\Infrastructure\Doctrine\CCQDeclarationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExportCCQHandler
{
    public function __construct(
        private CCQDeclarationRepository $declarations,
        private ExportService $exporter,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(ExportCCQMessage $message): void
    {
        $declaration = $this->declarations->find($message->ccqDeclarationId);
        if ($declaration === null) {
            return;
        }

        $path = $this->exporter->generatePdf($declaration);
        $declaration->markExported('ccq/'.basename($path), new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
