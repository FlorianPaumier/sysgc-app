<?php

declare(strict_types=1);

namespace App\Tests\Safety\Unit\Domain;

use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Safety\Domain\Model\SafetyLog;
use App\Safety\Domain\Enum\SafetyLogStatus;
use PHPUnit\Framework\TestCase;

final class SafetyLogTest extends TestCase
{
    public function test_records_a_complete_daily_safety_snapshot(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $checklist = [['label' => 'Casques de sécurité portés', 'checked' => true, 'comment' => '']];
        $photos = ['safety_photos/project/2026-06-09/hazard.jpg'];
        $signatures = [['workerName' => 'Camille Tremblay', 'dataUrl' => 'data:image/png;base64,signature']];

        $log = SafetyLog::record(
            project: $project,
            date: $date,
            weather: 'Ensoleillé',
            checklistItems: $checklist,
            hazardPhotos: $photos,
            workerSignatures: $signatures,
            incidentReport: 'Zone glissante balisée.',
        );

        self::assertSame($project, $log->getProject());
        self::assertEquals($date, $log->getDate());
        self::assertSame('Ensoleillé', $log->getWeather());
        self::assertSame($checklist, $log->getChecklistItems());
        self::assertSame($photos, $log->getHazardPhotos());
        self::assertSame($signatures, $log->getWorkerSignatures());
        self::assertSame('Zone glissante balisée.', $log->getIncidentReport());
    }

    public function test_new_log_is_a_draft_and_completed_logs_are_locked(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', [], [], [], null);

        self::assertSame(SafetyLogStatus::DRAFT, $log->getStatus());

        $log->complete();

        self::assertSame(SafetyLogStatus::COMPLETED, $log->getStatus());
        $this->expectException(\LogicException::class);
        $log->update('Pluie', [], [], [], null);
    }

    public function test_amendment_preserves_the_completed_snapshot(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $original = [['id' => 'baseline-1', 'label' => 'Casques', 'checked' => true, 'comment' => '', 'protected' => true]];
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', $original, ['before.jpg'], [], null);
        $log->complete();

        $log->amend('Pluie', $original, ['after.jpg'], [], 'Zone balisée.');

        self::assertSame(SafetyLogStatus::AMENDED, $log->getStatus());
        self::assertSame($original, $log->getOriginalChecklistItems());
        self::assertSame(['before.jpg'], $log->getOriginalHazardPhotos());
        self::assertSame('Zone balisée.', $log->getIncidentReport());
    }

    public function test_completed_and_amended_payloads_are_immutable_lifecycle_states(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', [], [], [], null);
        $log->complete();
        $log->amend('Pluie', [], [], [], 'Correction administrative');

        self::assertTrue($log->isReadOnly());
        self::assertSame('Ensoleillé', $log->getOriginalWeather());

        $this->expectException(\LogicException::class);
        $log->amend('Neige', [], [], [], 'Deuxième correction');
    }

    public function test_new_logs_have_a_sync_version_and_remember_client_events(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', [], [], [], null);

        self::assertSame(1, $log->getVersion());
        self::assertFalse($log->hasAppliedClientEvent('event-1'));
        $log->rememberClientEvent('event-1');
        self::assertTrue($log->hasAppliedClientEvent('event-1'));
        self::assertSame(1, $log->getVersion());
    }

    public function test_mutating_a_draft_increments_its_sync_version(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $log = SafetyLog::record($project, new \DateTimeImmutable('2026-06-09'), 'Ensoleillé', [], [], [], null);

        $log->update('Pluie', [], [], [], null);

        self::assertSame(2, $log->getVersion());
    }
}
