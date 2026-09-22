<?php

declare(strict_types=1);

namespace App\Compliance\Application\MessageHandler;

use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Compliance\Infrastructure\RbqScraper;
use App\Compliance\Message\BatchRbqCheckMessage;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class BatchRbqCheckMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubcontractorRepository $subcontractors,
        private RbqScraper $scraper,
        private CacheInterface $cache,
    ) {}

    public function __invoke(BatchRbqCheckMessage $message): void
    {
        $project = $this->entityManager->find(Project::class, Uuid::fromString($message->projectId));
        if (!$project instanceof Project) {
            return;
        }

        $completed = 0;
        $verified = 0;
        $manualReview = 0;
        $invalid = 0;
        $this->storeProgress($message->projectId, 'processing', count($message->licenseNumbers), $completed, $verified, $manualReview, $invalid);
        foreach ($message->licenseNumbers as $license) {
            $subcontractor = $this->subcontractors->findOneForProjectByLicense($project, $license);
            if (!$subcontractor instanceof Subcontractor) {
                ++$completed;
                $this->storeProgress($message->projectId, 'processing', count($message->licenseNumbers), $completed, $verified, $manualReview, $invalid);
                continue;
            }
            $result = $this->scraper->check($license);
            $subcontractor->recordAutomatedVerification($result, new \DateTimeImmutable());
            if (null !== $result->error) {
                ++$manualReview;
            } elseif ($result->found && 'valid' === $result->status) {
                ++$verified;
            } else {
                ++$invalid;
            }
            ++$completed;
            $this->storeProgress($message->projectId, 'processing', count($message->licenseNumbers), $completed, $verified, $manualReview, $invalid);
        }
        $this->entityManager->flush();
        $this->storeProgress($message->projectId, 'complete', count($message->licenseNumbers), $completed, $verified, $manualReview, $invalid);
    }

    private function storeProgress(string $projectId, string $status, int $total, int $completed, int $verified, int $manualReview, int $invalid): void
    {
        $key = 'rbq.batch.'.$projectId;
        $this->cache->delete($key);
        $this->cache->get($key, static fn (): array => [
            'status' => $status,
            'total' => $total,
            'completed' => $completed,
            'verified' => $verified,
            'manualReview' => $manualReview,
            'invalid' => $invalid,
        ]);
    }
}
