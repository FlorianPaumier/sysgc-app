<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Domain\Model\CCQDeclaration;
use PHPUnit\Framework\TestCase;

final class CCQDeclarationTest extends TestCase
{
    public function test_a_declaration_can_be_marked_submitted_with_a_pdf_receipt(): void
    {
        $declaration = (new CCQDeclaration())
            ->setStatus('draft')
            ->markSubmitted('exports/ccq/declaration.pdf', new \DateTimeImmutable('2026-06-30 10:15:00'));

        self::assertSame('submitted', $declaration->getStatus());
        self::assertSame('exports/ccq/declaration.pdf', $declaration->getReceiptPath());
        self::assertSame('2026-06-30 10:15', $declaration->getSubmittedAt()?->format('Y-m-d H:i'));
    }

    public function test_exported_declaration_can_be_filed_with_an_immutable_snapshot(): void
    {
        $hours = [['workerName' => 'Jeanne', 'week1Hours' => 40.0]];
        $declaration = (new CCQDeclaration())
            ->setDeclaration($hours)
            ->setRuleVersion('CCQ-2026.1')
            ->setStatus('draft');

        $declaration->markExported('exports/ccq/declaration.pdf', new \DateTimeImmutable('2026-06-30 10:15:00'));
        $declaration->markFiled(new \DateTimeImmutable('2026-06-30 10:20:00'));

        self::assertSame('filed', $declaration->getStatus());
        self::assertSame($hours, $declaration->getFiledSnapshot());
        self::assertSame('CCQ-2026.1', $declaration->getFiledRuleVersion());
        self::assertSame('2026-06-30 10:20', $declaration->getFiledAt()?->format('Y-m-d H:i'));

        $this->expectException(\LogicException::class);
        $declaration->setDeclaration([['workerName' => 'Changed']]);
    }

    public function test_rule_version_is_captured_with_the_filed_snapshot_and_cannot_change_after_filing(): void
    {
        $declaration = (new CCQDeclaration())
            ->setRuleVersion('CCQ-2026.2')
            ->setDeclaration([['workerName' => 'Jeanne', 'week1Hours' => 40.0]])
            ->setStatus('draft');

        $declaration->markExported('/uploads/ccq/declaration.pdf', new \DateTimeImmutable('2026-09-16 10:15:00'));
        $declaration->markFiled(new \DateTimeImmutable('2026-09-16 10:20:00'));

        self::assertSame('CCQ-2026.2', $declaration->getRuleVersion());
        self::assertSame('CCQ-2026.2', $declaration->getFiledRuleVersion());

        $this->expectException(\LogicException::class);
        $declaration->setRuleVersion('CCQ-2026.3');
    }

    public function test_outcome_is_optional_and_can_only_be_recorded_after_filing(): void
    {
        $declaration = (new CCQDeclaration())->setStatus('exported');

        $this->expectException(\LogicException::class);
        $declaration->recordOutcome('accepted');
    }

    public function test_filed_declaration_accepts_a_valid_outcome(): void
    {
        $declaration = (new CCQDeclaration())
            ->setDeclaration([])
            ->setStatus('draft');
        $declaration->markExported('exports/ccq/declaration.pdf', new \DateTimeImmutable());
        $declaration->markFiled(new \DateTimeImmutable());

        $declaration->recordOutcome('rejected');

        self::assertSame('rejected', $declaration->getOutcome());
    }
}
