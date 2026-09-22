<?php

declare(strict_types=1);

namespace App\Commercial\Application;

use App\Commercial\Domain\Enum\ChangeOrderStatus;
use App\Commercial\Domain\Model\ChangeOrder;
use App\Commercial\Infrastructure\Doctrine\ChangeOrderRepository;
use App\Identity\Domain\Model\User;

final readonly class ChangeOrderService
{
    public function __construct(private ChangeOrderRepository $changeOrders)
    {
    }

    public function create(ChangeOrder $changeOrder, User $actor): void
    {
        $changeOrder->recordCreated($actor->getEmail());
        $this->changeOrders->save($changeOrder);
    }

    /** @param array{title?: string, description?: ?string, costImpact?: string, scheduleImpactDays?: int} $changes */
    public function update(ChangeOrder $changeOrder, User $actor, array $changes): void
    {
        $details = [];
        if (array_key_exists('title', $changes) && $changes['title'] !== $changeOrder->getTitle()) {
            $details[] = 'Titre modifié';
            $changeOrder->setTitle($changes['title']);
        }
        if (array_key_exists('description', $changes) && $changes['description'] !== $changeOrder->getDescription()) {
            $details[] = 'Description modifiée';
            $changeOrder->setDescription($changes['description']);
        }
        if (array_key_exists('costImpact', $changes) && $changes['costImpact'] !== $changeOrder->getCostImpact()) {
            $details[] = 'Impact financier modifié';
            $changeOrder->setCostImpact($changes['costImpact']);
        }
        if (array_key_exists('scheduleImpactDays', $changes) && $changes['scheduleImpactDays'] !== $changeOrder->getScheduleImpactDays()) {
            $details[] = 'Impact sur l’échéancier modifié';
            $changeOrder->setScheduleImpactDays($changes['scheduleImpactDays']);
        }
        if ($details !== []) {
            $changeOrder->recordModified($actor->getEmail(), implode(', ', $details));
        }
        $this->changeOrders->save($changeOrder);
    }

    public function approve(ChangeOrder $changeOrder, User $actor): void
    {
        $this->transition($changeOrder, ChangeOrderStatus::APPROVED, $actor, 'Avenant approuvé');
    }

    public function reject(ChangeOrder $changeOrder, User $actor, ?string $reason = null): void
    {
        $detail = $reason === null || trim($reason) === '' ? 'Avenant rejeté' : 'Avenant rejeté : '.trim($reason);
        $this->transition($changeOrder, ChangeOrderStatus::REJECTED, $actor, $detail);
    }

    public function reopen(ChangeOrder $changeOrder, User $actor): void
    {
        $this->transition($changeOrder, ChangeOrderStatus::PENDING, $actor, 'Avenant rouvert pour révision');
    }

    public function markInvoiced(ChangeOrder $changeOrder, User $actor): void
    {
        $this->transition($changeOrder, ChangeOrderStatus::INVOICED, $actor, 'Avenant marqué comme facturé');
    }

    public function canTransition(ChangeOrder $changeOrder, ChangeOrderStatus $target): bool
    {
        return match ($changeOrder->getStatus()) {
            ChangeOrderStatus::PENDING => in_array($target, [ChangeOrderStatus::APPROVED, ChangeOrderStatus::REJECTED], true),
            ChangeOrderStatus::APPROVED => $target === ChangeOrderStatus::INVOICED,
            ChangeOrderStatus::REJECTED => $target === ChangeOrderStatus::PENDING,
            ChangeOrderStatus::INVOICED => false,
        };
    }

    private function transition(ChangeOrder $changeOrder, ChangeOrderStatus $target, User $actor, string $detail): void
    {
        if (! $this->canTransition($changeOrder, $target)) {
            throw new \DomainException(sprintf('Transition de %s vers %s non autorisée.', $changeOrder->getStatus()->value, $target->value));
        }
        $changeOrder->transitionTo($target, $actor->getEmail(), $detail);
        $this->changeOrders->save($changeOrder);
    }
}
