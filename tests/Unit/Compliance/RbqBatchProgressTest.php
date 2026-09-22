<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Presentation\Component\RbqBatchProgress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RbqBatchProgressTest extends TestCase
{
    public function test_progress_exposes_manual_review_and_invalid_counts(): void
    {
        $cache = new ArrayAdapter();
        $cache->get('rbq.batch.project-1', static fn (): array => [
            'status' => 'complete',
            'total' => 5,
            'completed' => 5,
            'verified' => 2,
            'manualReview' => 2,
            'invalid' => 1,
        ]);
        $component = new RbqBatchProgress($cache);
        $component->projectId = 'project-1';

        self::assertSame([
            'status' => 'complete',
            'total' => 5,
            'completed' => 5,
            'verified' => 2,
            'manualReview' => 2,
            'invalid' => 1,
        ], $component->getProgress());
    }
}
