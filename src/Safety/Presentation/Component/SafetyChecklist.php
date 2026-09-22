<?php

declare(strict_types=1);

namespace App\Safety\Presentation\Component;

use App\Project\Domain\Model\Project;
use App\Project\Infrastructure\Doctrine\ProjectRepository;
use App\Safety\Application\DailyLogService;
use App\Safety\Domain\Enum\SafetyLogStatus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * @phpstan-type ChecklistItem array{id?: string, label: string, checked: bool, comment: string, protected?: bool}
 * @phpstan-type Signature array{workerName: string, dataUrl: string}
 */
#[AsLiveComponent('SafetyChecklist')]
final class SafetyChecklist
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    /** @var list<array{id?: string, label: string, checked: bool, comment: string, protected?: bool}> */
    #[LiveProp(writable: true)]
    public array $checklistItems = [];

    /** @var list<string> */
    #[LiveProp(writable: true)]
    public array $photos = [];

    /** @var list<array{workerName: string, dataUrl: string}> */
    #[LiveProp(writable: true)]
    public array $signatures = [];

    #[LiveProp(writable: true)]
    public string $weather = '';

    #[LiveProp(writable: true)]
    public string $incidentReport = '';

    #[LiveProp(writable: true)]
    public string $newItemLabel = '';

    #[LiveProp]
    public string $status = SafetyLogStatus::DRAFT->value;

    #[LiveProp]
    public string $projectId = '';

    #[LiveProp]
    public string $date = '';

    #[LiveProp]
    public int $version = 1;

    public function mount(): void
    {
        if ([] === $this->checklistItems) {
            $this->checklistItems = $this->getDefaultChecklist();
        } else {
            $this->checklistItems = array_map(
                static fn (array $item, int $index): array => [
                    'id' => isset($item['id']) ? (string) $item['id'] : 'legacy-'.$index,
                    'label' => $item['label'],
                    'checked' => $item['checked'],
                    'comment' => $item['comment'],
                    'protected' => isset($item['protected']) && $item['protected'],
                ],
                $this->checklistItems,
                array_keys($this->checklistItems),
            );
        }
    }

    #[LiveAction]
    public function toggleItem(int $index): void
    {
        $this->assertEditable();
        if (! isset($this->checklistItems[$index])) {
            throw new \OutOfBoundsException('Cet élément de contrôle n’existe pas.');
        }

        $this->checklistItems[$index]['checked'] = ! $this->checklistItems[$index]['checked'];
    }

    #[LiveAction]
    public function addPhoto(string $path): void
    {
        $this->assertEditable();
        if (count($this->photos) >= 5) {
            throw new \DomainException('Un rapport de sécurité peut contenir au maximum 5 photos.');
        }

        $this->photos[] = $path;
    }

    #[LiveAction]
    public function addSignature(string $workerName, string $dataUrl): void
    {
        $this->assertEditable();
        $this->signatures[] = ['workerName' => $workerName, 'dataUrl' => $dataUrl];
    }

    #[LiveAction]
    public function addCustomItem(string $label): void
    {
        $this->assertEditable();
        $label = trim($label);
        if ('' === $label) {
            throw new \InvalidArgumentException('Le libellé de la vérification est requis.');
        }

        $this->checklistItems[] = [
            'id' => 'custom-'.bin2hex(random_bytes(8)),
            'label' => $label,
            'checked' => false,
            'comment' => '',
            'protected' => false,
        ];
        $this->newItemLabel = '';
    }

    #[LiveAction]
    public function addOptionalItem(): void
    {
        $this->addCustomItem($this->newItemLabel);
    }

    #[LiveAction]
    public function save(ProjectRepository $projects, DailyLogService $logs): void
    {
        $this->assertEditable();
        $project = $projects->find($this->projectId);
        if (! $project instanceof Project) {
            throw new \LogicException('Le chantier du rapport de sécurité est introuvable.');
        }

        $log = $logs->createOrUpdate($project, new \DateTimeImmutable($this->date), [
            'weather' => $this->weather,
            'checklistItems' => $this->checklistItems,
            'hazardPhotos' => $this->photos,
            'workerSignatures' => $this->signatures,
            'incidentReport' => '' === trim($this->incidentReport) ? null : $this->incidentReport,
        ]);

        $this->dispatchBrowserEvent('safety:saved', ['logId' => (string) $log->getId()]);
        $this->version = $log->getVersion();
    }

    /** @param array{weather?: string, incidentReport?: string|null} $scalarChoices */
    #[LiveAction]
    public function sync(ProjectRepository $projects, DailyLogService $logs, string $clientEventId, int $baseVersion, array $scalarChoices = []): void
    {
        $this->assertEditable();
        $project = $projects->find($this->projectId);
        if (! $project instanceof Project) {
            throw new \LogicException('Le chantier du rapport de sécurité est introuvable.');
        }

        $result = $logs->sync($project, new \DateTimeImmutable($this->date), $this->payload(), $baseVersion, $clientEventId, $scalarChoices);
        $this->version = $result->log->getVersion();
        $this->checklistItems = $result->log->getChecklistItems();
        $this->photos = $result->log->getHazardPhotos();
        $this->signatures = $result->log->getWorkerSignatures();
        $this->weather = $result->log->getWeather();
        $this->incidentReport = $result->log->getIncidentReport() ?? '';
        $this->dispatchBrowserEvent('safety:synced', ['conflicts' => $result->conflicts, 'duplicate' => $result->duplicate]);
    }

    #[LiveAction]
    public function complete(ProjectRepository $projects, DailyLogService $logs): void
    {
        $this->assertEditable();
        $project = $projects->find($this->projectId);
        if (! $project instanceof Project) {
            throw new \LogicException('Le chantier du rapport de sécurité est introuvable.');
        }

        $log = $logs->complete($project, new \DateTimeImmutable($this->date), $this->payload());
        $this->status = $log->getStatus()->value;
        $this->dispatchBrowserEvent('safety:completed', ['logId' => (string) $log->getId()]);
    }

    public function getCompletedCount(): int
    {
        return count(array_filter($this->checklistItems, static fn (array $item): bool => $item['checked']));
    }

    /** @return list<ChecklistItem> */
    public function getDefaultChecklist(): array
    {
        return array_map(static fn (string $label, int $index): array => [
            'id' => 'baseline-'.($index + 1),
            'label' => $label,
            'checked' => false,
            'comment' => '',
            'protected' => true,
        ], [
            'Casques de sécurité portés',
            'Bottes de sécurité portées',
            'Gilets haute visibilité portés',
            'Échafaudages inspectés',
            'Extincteurs accessibles',
            'Trousse de premiers soins disponible',
            'Sorties de secours dégagées',
            'Équipement électrique vérifié',
            'Signalisation de chantier en place',
            'Réunion de sécurité tenue',
        ], array_keys([
            'Casques de sécurité portés',
            'Bottes de sécurité portées',
            'Gilets haute visibilité portés',
            'Échafaudages inspectés',
            'Extincteurs accessibles',
            'Trousse de premiers soins disponible',
            'Sorties de secours dégagées',
            'Équipement électrique vérifié',
            'Signalisation de chantier en place',
            'Réunion de sécurité tenue',
        ]));
    }

    /** @return array{weather: string, checklistItems: list<ChecklistItem>, hazardPhotos: list<string>, workerSignatures: list<Signature>, incidentReport: string|null} */
    private function payload(): array
    {
        return [
            'weather' => $this->weather,
            'checklistItems' => $this->checklistItems,
            'hazardPhotos' => $this->photos,
            'workerSignatures' => $this->signatures,
            'incidentReport' => '' === trim($this->incidentReport) ? null : $this->incidentReport,
        ];
    }

    private function assertEditable(): void
    {
        if (SafetyLogStatus::DRAFT->value !== $this->status) {
            throw new \LogicException('Ce rapport est en lecture seule.');
        }
    }
}
