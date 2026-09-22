<?php

declare(strict_types=1);

namespace AppTests\Unit\Subscription;

use App\Identity\Domain\Enum\SubscriptionStatus;
use App\Identity\Domain\Enum\SubscriptionTier;
use App\Identity\Domain\Model\Organization;
use PHPUnit\Framework\TestCase;

final class SubscriptionStateTest extends TestCase
{
    public function test_active_subscription_is_writable(): void
    {
        $organization = new Organization();
        $organization->setSubscriptionTier(SubscriptionTier::Pro);
        $organization->setSubscriptionExpiresAt(new \DateTimeImmutable('2026-09-30'));

        self::assertSame(SubscriptionStatus::Active, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-16')));
        self::assertFalse($organization->isReadOnly(new \DateTimeImmutable('2026-09-16')));
    }

    public function test_expired_subscription_has_seven_day_grace_then_becomes_read_only(): void
    {
        $organization = new Organization();
        $organization->setSubscriptionTier(SubscriptionTier::Pro);
        $organization->setSubscriptionExpiresAt(new \DateTimeImmutable('2026-09-10'));

        self::assertSame(SubscriptionStatus::Grace, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-12')));
        self::assertFalse($organization->isReadOnly(new \DateTimeImmutable('2026-09-12')));
        self::assertSame(SubscriptionStatus::ReadOnly, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-18')));
        self::assertTrue($organization->isReadOnly(new \DateTimeImmutable('2026-09-18')));
    }

    public function test_business_overflow_requires_enterprise_escalation(): void
    {
        $organization = new Organization();
        $organization->setSubscriptionTier(SubscriptionTier::Business);

        self::assertTrue($organization->hasWorkerOverflow(51));
        self::assertFalse($organization->hasWorkerOverflow(50));
        self::assertSame(SubscriptionStatus::EnterpriseRequired, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-16'), 51));
    }

    public function test_grace_is_inclusive_for_seven_days_and_read_only_afterwards(): void
    {
        $organization = new Organization();
        $organization->setSubscriptionTier(SubscriptionTier::Business);
        $organization->setSubscriptionExpiresAt(new \DateTimeImmutable('2026-09-10 12:00:00'));

        self::assertSame(SubscriptionStatus::Grace, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-17 12:00:00')));
        self::assertSame(SubscriptionStatus::ReadOnly, $organization->subscriptionStatus(new \DateTimeImmutable('2026-09-17 12:00:01')));
    }

    public function test_deletion_is_due_only_after_recovery_window(): void
    {
        $organization = new Organization();
        $scheduledAt = new \DateTimeImmutable('2026-09-01 10:00:00');
        $organization->scheduleDeletion($scheduledAt);

        self::assertFalse($organization->isDeletionDue(new \DateTimeImmutable('2026-09-30 10:00:00')));
        self::assertTrue($organization->isDeletionDue(new \DateTimeImmutable('2026-10-01 10:00:01')));
    }
}
