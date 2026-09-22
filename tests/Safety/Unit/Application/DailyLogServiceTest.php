<?php

declare(strict_types=1);

namespace App\Tests\Safety\Unit\Application;

use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Safety\Application\DailyLogService;
use App\Safety\Domain\Model\SafetyLog;
use App\Safety\Infrastructure\Doctrine\SafetyLogRepository;
use PHPUnit\Framework\TestCase;

final class DailyLogServiceTest extends TestCase
{
    public function test_creates_a_log_when_no_log_exists_for_the_project_date(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->with($project, $date)->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::isInstanceOf(SafetyLog::class), true);

        $log = (new DailyLogService($repository))->createOrUpdate($project, $date, $this->data());

        self::assertSame('Nuageux', $log->getWeather());
        self::assertSame('Incident mineur', $log->getIncidentReport());
    }

    public function test_updates_the_existing_log_for_the_same_project_date(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $existing = SafetyLog::record($project, $date, 'Ensoleillé', [], [], [], null);
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->willReturn($existing);
        $repository->expects(self::once())->method('save')->with($existing, true);

        $log = (new DailyLogService($repository))->createOrUpdate($project, $date, $this->data());

        self::assertSame($existing, $log);
        self::assertSame('Nuageux', $log->getWeather());
    }

    public function test_amends_a_completed_log_and_keeps_the_original_snapshot(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $existing = SafetyLog::record($project, $date, 'Ensoleillé', [], [], [], null);
        $existing->complete();
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->willReturn($existing);
        $repository->expects(self::once())->method('save')->with($existing, true);

        $log = (new DailyLogService($repository))->amend($project, $date, $this->data());

        self::assertSame('Nuageux', $log->getWeather());
        self::assertSame('Ensoleillé', $log->getOriginalWeather());
    }

    public function test_replaying_a_client_event_does_not_mutate_the_log(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $existing = SafetyLog::record($project, $date, 'Ensoleillé', [], [], [], null);
        $existing->rememberClientEvent('event-1');
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->willReturn($existing);
        $repository->expects(self::never())->method('save');

        $result = (new DailyLogService($repository))->sync($project, $date, $this->data(), 1, 'event-1');

        self::assertSame($existing, $result->log);
        self::assertSame(1, $result->log->getVersion());
    }

    public function test_sync_merges_new_evidence_and_reports_scalar_conflicts(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $existing = SafetyLog::record($project, $date, 'Ensoleillé', [['id' => 'baseline-1', 'label' => 'Casques', 'checked' => true, 'comment' => '']], ['before.jpg'], [], null);
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->willReturn($existing);
        $repository->expects(self::once())->method('save')->with($existing, true);

        $data = $this->data();
        $data['weather'] = 'Pluie';
        $data['checklistItems'] = [
            ['id' => 'baseline-1', 'label' => 'Casques', 'checked' => true, 'comment' => '', 'protected' => false],
            ['id' => 'custom-1', 'label' => 'Zone', 'checked' => false, 'comment' => '', 'protected' => false],
        ];
        $data['hazardPhotos'][] = 'after.jpg';
        $result = (new DailyLogService($repository))->sync($project, $date, $data, 0, 'event-2');

        self::assertContains('weather', $result->conflicts);
        self::assertSame(['before.jpg', 'safety_photos/project/2026-06-09/hazard.jpg', 'after.jpg'], $result->log->getHazardPhotos());
        self::assertCount(2, $result->log->getChecklistItems());
    }

    public function test_sync_cannot_overwrite_a_completed_log(): void
    {
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');
        $date = new \DateTimeImmutable('2026-06-09');
        $existing = SafetyLog::record($project, $date, 'Ensoleillé', [], [], [], null);
        $existing->complete();
        $repository = $this->createMock(SafetyLogRepository::class);
        $repository->expects(self::once())->method('findOneByProjectAndDate')->willReturn($existing);
        $repository->expects(self::never())->method('save');

        $this->expectException(\LogicException::class);
        (new DailyLogService($repository))->sync($project, $date, $this->data(), 1, 'event-new');
    }

    /** @return array{weather: string, checklistItems: list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}>, hazardPhotos: list<string>, workerSignatures: list<array{workerName: string, dataUrl: string}>, incidentReport: string|null} */
    private function data(): array
    {
        return [
            'weather' => 'Nuageux',
            'checklistItems' => [['id' => 'initial-1', 'label' => 'Casques', 'checked' => true, 'comment' => '', 'protected' => false]],
            'hazardPhotos' => ['safety_photos/project/2026-06-09/hazard.jpg'],
            'workerSignatures' => [['workerName' => 'Camille', 'dataUrl' => 'data:image/png;base64,a']],
            'incidentReport' => 'Incident mineur',
        ];
    }
}
