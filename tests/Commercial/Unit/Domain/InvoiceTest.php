<?php

declare(strict_types=1);

namespace App\Tests\Commercial\Unit\Domain;

use App\Commercial\Domain\Enum\InvoiceStatus;
use App\Commercial\Domain\Model\Invoice;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function test_invoice_tracks_milestone_amount_and_payment_transitions(): void
    {
        $invoice = (new Invoice())
            ->setMilestoneName('Fondations')
            ->setPercentage(25)
            ->setAmount('25000.00')
            ->markSent()
            ->markPaid();

        self::assertSame('Fondations', $invoice->getMilestoneName());
        self::assertSame(25, $invoice->getPercentage());
        self::assertSame('25000.00', $invoice->getAmount());
        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        self::assertNotNull($invoice->getSentAt());
        self::assertNotNull($invoice->getPaidAt());
    }
}
