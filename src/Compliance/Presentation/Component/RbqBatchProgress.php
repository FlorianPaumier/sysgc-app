<?php

declare(strict_types=1);

namespace App\Compliance\Presentation\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Contracts\Cache\CacheInterface;

#[AsLiveComponent('RbqBatchProgress')]
final class RbqBatchProgress
{
    use DefaultActionTrait;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    #[LiveProp]
    public string $projectId = '';

    /** @return array{status: string, total: int, completed: int, verified: int, manualReview: int, invalid: int} */
    public function getProgress(): array
    {
        /** @var array{status?: string, total?: int, completed?: int, verified?: int, manualReview?: int, invalid?: int} $progress */
        $progress = $this->cache->get('rbq.batch.'.$this->projectId, static fn (): array => []);

        return [
            'status' => $progress['status'] ?? 'idle',
            'total' => $progress['total'] ?? 0,
            'completed' => $progress['completed'] ?? 0,
            'verified' => $progress['verified'] ?? 0,
            'manualReview' => $progress['manualReview'] ?? 0,
            'invalid' => $progress['invalid'] ?? 0,
        ];
    }
}
