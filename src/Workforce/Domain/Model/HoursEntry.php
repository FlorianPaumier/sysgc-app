<?php

declare(strict_types=1);

namespace App\Workforce\Domain\Model;

use App\Project\Domain\Model\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class HoursEntry
{
    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $weekStart = null;

    /** @var array<string, int|float> */
    #[ORM\Column(type: Types::JSON)]
    private array $hours = [];

    #[ORM\ManyToOne(inversedBy: 'hoursEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Worker $worker = null;

    #[ORM\ManyToOne]
    // Legacy entries created before project-scoping may not have a project.
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekStart(): ?\DateTime
    {
        return $this->weekStart;
    }

    public function setWeekStart(\DateTime $weekStart): static
    {
        $this->weekStart = $weekStart;

        return $this;
    }

    /** @return array<string, int|float> */
    public function getHours(): array
    {
        return $this->hours;
    }

    /** @param array<string, int|float> $hours */
    public function setHours(array $hours): static
    {
        $this->hours = $hours;

        return $this;
    }

    public function getWorker(): ?Worker
    {
        return $this->worker;
    }

    public function setWorker(?Worker $worker): static
    {
        $this->worker = $worker;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }
}
