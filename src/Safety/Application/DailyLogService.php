<?php

declare(strict_types=1);

namespace App\Safety\Application;

use App\Project\Domain\Model\Project;
use App\Safety\Domain\Model\SafetyLog;
use App\Safety\Infrastructure\Doctrine\SafetyLogRepository;

/**
 * @phpstan-type ChecklistItem array{id?: string, label: string, checked: bool, comment: string, protected?: bool}
 * @phpstan-type Signature array{workerName: string, dataUrl: string}
 * @phpstan-type SafetyPayload array{weather: string, checklistItems: list<ChecklistItem>, hazardPhotos: list<string>, workerSignatures: list<Signature>, incidentReport: string|null}
 */
final readonly class DailyLogService
{
    public function __construct(private SafetyLogRepository $logs)
    {
    }

    /**
     * @param SafetyPayload $data
     */
    public function createOrUpdate(Project $project, \DateTimeInterface $date, array $data): SafetyLog
    {
        $log = $this->logs->findOneByProjectAndDate($project, $date);

        if (null === $log) {
            $log = SafetyLog::record(
                project: $project,
                date: $date,
                weather: $data['weather'],
                checklistItems: $data['checklistItems'],
                hazardPhotos: $data['hazardPhotos'],
                workerSignatures: $data['workerSignatures'],
                incidentReport: $data['incidentReport'],
            );
        } else {
            $log->update(
                weather: $data['weather'],
                checklistItems: $data['checklistItems'],
                hazardPhotos: $data['hazardPhotos'],
                workerSignatures: $data['workerSignatures'],
                incidentReport: $data['incidentReport'],
            );
        }

        $this->logs->save($log, true);

        return $log;
    }

    /**
     * Apply one offline event. Evidence is additive; scalar fields are only
     * changed when the client has the current version or explicitly chooses it.
     *
     * @param array{weather: string, checklistItems: list<ChecklistItem>, hazardPhotos: list<string>, workerSignatures: list<Signature>, incidentReport: string|null} $data
     * @param array{weather?: string, incidentReport?: string|null} $scalarChoices
     */
    public function sync(Project $project, \DateTimeInterface $date, array $data, int $baseVersion, string $clientEventId, array $scalarChoices = []): SafetySyncResult
    {
        $log = $this->logs->findOneByProjectAndDate($project, $date);
        if (null === $log) {
            $log = SafetyLog::record($project, $date, $data['weather'], $data['checklistItems'], $data['hazardPhotos'], $data['workerSignatures'], $data['incidentReport']);
            $log->rememberClientEvent($clientEventId);
            $this->logs->save($log, true);

            return new SafetySyncResult($log);
        }
        if ($log->hasAppliedClientEvent($clientEventId)) {
            return new SafetySyncResult($log, [], true);
        }
        if ($log->isReadOnly()) {
            throw new \LogicException('Un rapport complété ou amendé ne peut pas être remplacé par une modification hors ligne.');
        }

        $conflicts = [];
        $weather = $data['weather'];
        $incidentReport = $data['incidentReport'];
        if ($baseVersion !== $log->getVersion()) {
            if ($weather !== $log->getWeather() && (($scalarChoices['weather'] ?? null) !== 'client')) {
                $conflicts[] = 'weather';
                $weather = $log->getWeather();
            }
            if ($incidentReport !== $log->getIncidentReport() && (($scalarChoices['incidentReport'] ?? null) !== 'client')) {
                $conflicts[] = 'incidentReport';
                $incidentReport = $log->getIncidentReport();
            }
        }

        $checklist = $this->mergeChecklist($log->getChecklistItems(), $data['checklistItems']);
        $photos = array_values(array_unique(array_merge($log->getHazardPhotos(), $data['hazardPhotos'])));
        $signatures = $this->mergeSignatures($log->getWorkerSignatures(), $data['workerSignatures']);
        $log->update($weather, $checklist, $photos, $signatures, $incidentReport);
        $log->rememberClientEvent($clientEventId);
        $this->logs->save($log, true);

        return new SafetySyncResult($log, $conflicts);
    }

    /**
     * @param list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}> $current
     * @param list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}> $incoming
     * @return list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}>
     */
    private function mergeChecklist(array $current, array $incoming): array
    {
        $ids = [];
        foreach ($current as $item) {
            $ids[(string) ($item['id'] ?? 'legacy-'.$item['label'])] = true;
        }
        foreach ($incoming as $item) {
            $id = (string) ($item['id'] ?? 'legacy-'.$item['label']);
            if (! isset($ids[$id])) {
                $current[] = $item;
                $ids[$id] = true;
            }
        }

        return $current;
    }

    /**
     * @param list<array{workerName: string, dataUrl: string}> $current
     * @param list<array{workerName: string, dataUrl: string}> $incoming
     * @return list<array{workerName: string, dataUrl: string}>
     */
    private function mergeSignatures(array $current, array $incoming): array
    {
        $keys = array_map(static fn (array $signature): string => $signature['workerName'].'|'.$signature['dataUrl'], $current);
        foreach ($incoming as $signature) {
            $key = $signature['workerName'].'|'.$signature['dataUrl'];
            if (! in_array($key, $keys, true)) {
                $current[] = $signature;
                $keys[] = $key;
            }
        }

        return $current;
    }

    /**
     * Persist the current draft and lock it as a completed report.
     *
     * @param SafetyPayload $data
     */
    public function complete(Project $project, \DateTimeInterface $date, array $data): SafetyLog
    {
        $log = $this->createOrUpdate($project, $date, $data);
        $log->complete();
        $this->logs->save($log, true);

        return $log;
    }

    /**
     * Apply an explicit administrative correction while preserving the completed payload.
     *
     * @param SafetyPayload $data
     */
    public function amend(Project $project, \DateTimeInterface $date, array $data): SafetyLog
    {
        $log = $this->logs->findOneByProjectAndDate($project, $date);
        if (null === $log) {
            throw new \LogicException('Le rapport à amender est introuvable.');
        }

        $log->amend(
            weather: $data['weather'],
            checklistItems: $data['checklistItems'],
            hazardPhotos: $data['hazardPhotos'],
            workerSignatures: $data['workerSignatures'],
            incidentReport: $data['incidentReport'],
        );
        $this->logs->save($log, true);

        return $log;
    }

    /**
     * @return list<SafetyLog>
     */
    public function findByProject(Project $project, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return $this->logs->findByProject($project, $from, $to);
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    public function getMissingDays(Project $project, \DateTimeInterface $since): array
    {
        $start = \DateTimeImmutable::createFromInterface($since)->setTime(0, 0);
        $today = new \DateTimeImmutable('today');
        $loggedDates = [];

        foreach ($this->logs->findByProject($project, $start, $today) as $log) {
            $date = $log->getDate();
            if (null !== $date) {
                $loggedDates[$date->format('Y-m-d')] = true;
            }
        }

        $missing = [];
        for ($date = $start; $date <= $today; $date = $date->modify('+1 day')) {
            if (! isset($loggedDates[$date->format('Y-m-d')])) {
                $missing[] = $date;
            }
        }

        return $missing;
    }
}
