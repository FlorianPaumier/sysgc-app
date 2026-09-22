<?php

declare(strict_types=1);

namespace AppTests\Commercial\Unit\Application;

use App\Commercial\Application\ChangeOrderService;
use App\Commercial\Domain\Enum\ChangeOrderStatus;
use App\Commercial\Domain\Model\ChangeOrder;
use App\Commercial\Infrastructure\Doctrine\ChangeOrderRepository;
use App\Identity\Domain\Model\User;
use PHPUnit\Framework\TestCase;

final class ChangeOrderServiceTest extends TestCase
{
    public function testApproveAppendsAnAuditEntryAndRecordsApprover(): void
    {
        $repository = $this->createMock(ChangeOrderRepository::class);
        $repository->expects(self::once())->method('save')->with(self::isInstanceOf(ChangeOrder::class));
        $service = new ChangeOrderService($repository);
        $order = new ChangeOrder();
        $actor = (new User())->setEmail('owner@example.com');
        $order->recordCreated('requester@example.com');

        $service->approve($order, $actor);

        self::assertSame(ChangeOrderStatus::APPROVED, $order->getStatus());
        self::assertSame('owner@example.com', $order->getApprovedBy());
        self::assertSame('approved', $order->getAuditTrail()[1]['action']);
        self::assertSame('owner@example.com', $order->getAuditTrail()[1]['user']);
    }

    public function testRejectAppendsReasonToAuditTrail(): void
    {
        $repository = $this->createMock(ChangeOrderRepository::class);
        $repository->expects(self::once())->method('save')->with(self::isInstanceOf(ChangeOrder::class));
        $service = new ChangeOrderService($repository);
        $order = new ChangeOrder();
        $actor = (new User())->setEmail('admin@example.com');

        $service->reject($order, $actor, 'Le prix doit être révisé.');

        self::assertSame(ChangeOrderStatus::REJECTED, $order->getStatus());
        self::assertStringContainsString('Le prix doit être révisé.', $order->getAuditTrail()[0]['detail']);
    }

    public function testInvalidTransitionIsRejected(): void
    {
        $repository = $this->createStub(ChangeOrderRepository::class);
        $service = new ChangeOrderService($repository);
        $order = new ChangeOrder();
        $order->transitionTo(ChangeOrderStatus::APPROVED, 'owner@example.com', 'Approuvé');

        $this->expectException(\DomainException::class);
        $service->reject($order, (new User())->setEmail('admin@example.com'), 'Non conforme.');
    }

    public function testUpdateAppendsOnlyChangedFieldsToAuditTrail(): void
    {
        $repository = $this->createMock(ChangeOrderRepository::class);
        $repository->expects(self::once())->method('save')->with(self::isInstanceOf(ChangeOrder::class));
        $service = new ChangeOrderService($repository);
        $order = (new ChangeOrder())->setTitle('Ancien titre')->setCostImpact('100.00');
        $actor = (new User())->setEmail('editor@example.com');

        $service->update($order, $actor, ['title' => 'Nouveau titre', 'costImpact' => '125.00']);

        self::assertSame('Nouveau titre', $order->getTitle());
        self::assertSame('125.00', $order->getCostImpact());
        self::assertSame('modified', $order->getAuditTrail()[0]['action']);
        self::assertStringContainsString('Titre', $order->getAuditTrail()[0]['detail']);
    }
}
