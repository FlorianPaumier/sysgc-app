<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SubscriptionTier;
use PHPUnit\Framework\TestCase;

final class SubscriptionTierTest extends TestCase
{
    public function test_starter_worker_limit_is_5(): void
    {
        self::assertSame(5, SubscriptionTier::Starter->workerLimit());
    }

    public function test_pro_worker_limit_is_20(): void
    {
        self::assertSame(20, SubscriptionTier::Pro->workerLimit());
    }

    public function test_business_worker_limit_is_50(): void
    {
        self::assertSame(50, SubscriptionTier::Business->workerLimit());
    }

    public function test_starter_label(): void
    {
        self::assertSame('Starter', SubscriptionTier::Starter->label());
    }

    public function test_pro_label(): void
    {
        self::assertSame('Pro', SubscriptionTier::Pro->label());
    }

    public function test_business_label(): void
    {
        self::assertSame('Business', SubscriptionTier::Business->label());
    }

    public function test_from_value_round_trips(): void
    {
        self::assertSame(SubscriptionTier::Starter, SubscriptionTier::from('starter'));
        self::assertSame(SubscriptionTier::Pro, SubscriptionTier::from('pro'));
        self::assertSame(SubscriptionTier::Business, SubscriptionTier::from('business'));
    }
}
