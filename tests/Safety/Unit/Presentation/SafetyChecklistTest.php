<?php

declare(strict_types=1);

namespace App\Tests\Safety\Unit\Presentation;

use App\Safety\Presentation\Component\SafetyChecklist;
use PHPUnit\Framework\TestCase;

final class SafetyChecklistTest extends TestCase
{
    public function test_toggling_a_checklist_item_updates_the_completed_counter(): void
    {
        $checklist = new SafetyChecklist();
        $checklist->checklistItems = [
            ['id' => 'baseline-1', 'label' => 'Casques de sécurité portés', 'checked' => false, 'comment' => '', 'protected' => true],
            ['id' => 'baseline-2', 'label' => 'Bottes de sécurité portées', 'checked' => true, 'comment' => '', 'protected' => true],
        ];

        $checklist->toggleItem(0);

        self::assertTrue($checklist->checklistItems[0]['checked']);
        self::assertSame(2, $checklist->getCompletedCount());
    }

    public function test_refuses_to_add_more_than_five_photos(): void
    {
        $checklist = new SafetyChecklist();
        $checklist->photos = ['1.jpg', '2.jpg', '3.jpg', '4.jpg', '5.jpg'];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('5 photos');
        $checklist->addPhoto('6.jpg');
    }

    public function test_mounting_a_new_report_loads_the_ten_standard_safety_checks(): void
    {
        $checklist = new SafetyChecklist();

        $checklist->mount();

        self::assertCount(10, $checklist->checklistItems);
        self::assertSame('Casques de sécurité portés', $checklist->checklistItems[0]['label']);
        self::assertSame(true, $checklist->checklistItems[0]['protected'] ?? null);
        self::assertNotEmpty($checklist->checklistItems[0]['id'] ?? null);
    }

    public function test_can_add_an_optional_checklist_item_without_changing_baseline_items(): void
    {
        $checklist = new SafetyChecklist();
        $checklist->mount();

        $checklist->addCustomItem('Permis de travail affiché');

        self::assertCount(11, $checklist->checklistItems);
        self::assertSame(true, $checklist->checklistItems[0]['protected'] ?? null);
        self::assertSame(false, $checklist->checklistItems[10]['protected'] ?? null);
        self::assertSame('Permis de travail affiché', $checklist->checklistItems[10]['label']);
    }
}
