<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use App\Workforce\Domain\Model\Worker;
use PHPUnit\Framework\TestCase;

final class WorkerTest extends TestCase
{
    public function test_a_worker_has_one_ccq_trade_and_one_region(): void
    {
        $worker = new Worker();

        $worker->setCcqTrade(CcqTrade::Electrician);
        $worker->setCcqRegion(CcqRegion::Montreal);

        self::assertSame(CcqTrade::Electrician, $worker->getCcqTrade());
        self::assertSame(CcqRegion::Montreal, $worker->getCcqRegion());
    }
}
