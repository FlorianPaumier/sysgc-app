<?php

declare(strict_types=1);

namespace App\Tests\Commercial\Unit\Domain;

use App\Commercial\Domain\Enum\ChangeOrderStatus;
use App\Commercial\Domain\Model\ChangeOrder;
use PHPUnit\Framework\TestCase;

final class ChangeOrderTest extends TestCase
{
    public function test_state_transition_records_an_audit_entry(): void
    {
        $changeOrder = (new ChangeOrder())
            ->setTitle('Déplacement d’une conduite')
            ->setCostImpact('1200.00')
            ->setScheduleImpactDays(2)
            ->recordCreated('pmartin@construction.qc.ca');

        $changeOrder->transitionTo(ChangeOrderStatus::APPROVED, 'client@example.com', 'Approuvé par le client');

        self::assertSame(ChangeOrderStatus::APPROVED, $changeOrder->getStatus());
        self::assertCount(2, $changeOrder->getAuditTrail());
        self::assertSame('approved', $changeOrder->getAuditTrail()[1]['action']);
    }

    public function test_audit_entries_are_append_only_and_a_noop_does_not_create_history(): void
    {
        $changeOrder = (new ChangeOrder())->recordCreated('owner@example.com');
        $created = $changeOrder->getAuditTrail();

        $changeOrder->recordModified('owner@example.com', 'Titre modifié');

        self::assertCount(2, $changeOrder->getAuditTrail());
        self::assertSame($created[0], $changeOrder->getAuditTrail()[0]);
        self::assertSame('modified', $changeOrder->getAuditTrail()[1]['action']);
    }
}
