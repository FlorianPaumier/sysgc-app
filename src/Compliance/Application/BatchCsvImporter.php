<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Infrastructure\Doctrine\SubcontractorRepository;
use App\Compliance\Message\BatchRbqCheckMessage;
use App\Project\Domain\Model\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class BatchCsvImporter
{
    public function __construct(
        private SubcontractorRepository $subcontractors,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
    ) {}

    /**
     * @return array{created: int, updated: int, queued: int}
     */
    public function import(Project $project, string $csv): array
    {
        $rows = $this->rows($csv);
        $created = 0;
        $updated = 0;
        $licenses = [];

        foreach ($rows as $row) {
            $license = $row['rbq_license'] ?: null;
            $subcontractor = $license ? $this->subcontractors->findOneForProjectByLicense($project, $license) : null;

            if ($subcontractor instanceof Subcontractor) {
                ++$updated;
            } else {
                $subcontractor = new Subcontractor();
                $project->addSubcontractor($subcontractor);
                ++$created;
            }

            $subcontractor
                ->setName($row['name'])
                ->setRbqLicenseNumber($license)
                ->setCategory($row['category'] ?: null);
            $this->entityManager->persist($subcontractor);

            if ($license) {
                $licenses[] = $license;
            }
        }

        $this->entityManager->flush();

        if ($licenses !== []) {
            $this->bus->dispatch(new BatchRbqCheckMessage((string) $project->getId(), $licenses));
        }

        return ['created' => $created, 'updated' => $updated, 'queued' => count($licenses)];
    }

    /** @return list<array{name: string, rbq_license: string, category: string}> */
    private function rows(string $csv): array
    {
        $lines = preg_split('/\R/', trim($csv)) ?: [];
        $header = str_getcsv(array_shift($lines) ?? '', ',', '"', '\\');
        if ($header !== ['name', 'rbq_license', 'category']) {
            throw new \InvalidArgumentException('Le CSV doit contenir les colonnes name, rbq_license, category.');
        }
        if (count($lines) > 200) {
            throw new \InvalidArgumentException('Un import est limité à 200 sous-traitants.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, ',', '"', '\\');
            if (count($values) !== 3 || !is_string($values[0]) || !is_string($values[1]) || !is_string($values[2]) || trim($values[0]) === '') {
                throw new \InvalidArgumentException('Chaque ligne CSV doit fournir un nom et trois colonnes.');
            }
            $rows[] = [
                'name' => trim($values[0]),
                'rbq_license' => trim($values[1]),
                'category' => trim($values[2]),
            ];
        }

        return $rows;
    }
}
