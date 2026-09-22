<?php

declare(strict_types=1);

namespace App\Compliance\Application\MessageHandler;

use App\Compliance\Application\LicenseVerifier;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Message\CheckSingleLicenseMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CheckSingleLicenseMessageHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private LicenseVerifier $verifier) {}

    public function __invoke(CheckSingleLicenseMessage $message): void
    {
        $subcontractor = $this->entityManager->find(Subcontractor::class, Uuid::fromString($message->subcontractorId));
        if ($subcontractor instanceof Subcontractor) {
            $this->verifier->verifySingle($subcontractor);
        }
    }
}
