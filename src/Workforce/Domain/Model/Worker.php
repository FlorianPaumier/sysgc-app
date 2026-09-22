<?php

declare(strict_types=1);

namespace App\Workforce\Domain\Model;

use App\Identity\Domain\Model\Organization;
use App\Workforce\Domain\Enum\CcqRegion;
use App\Workforce\Domain\Enum\CcqTrade;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Worker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(enumType: CcqTrade::class)]
    private ?CcqTrade $ccqTrade = null;

    #[ORM\Column(enumType: CcqRegion::class)]
    private ?CcqRegion $ccqRegion = null;

    #[ORM\ManyToOne(inversedBy: 'workers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    /**
     * @var Collection<int, HoursEntry>
     */
    #[ORM\OneToMany(targetEntity: HoursEntry::class, mappedBy: 'worker', orphanRemoval: true)]
    private Collection $hoursEntries;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
        $this->hoursEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCcqTrade(): ?CcqTrade
    {
        return $this->ccqTrade;
    }

    public function setCcqTrade(CcqTrade $ccqTrade): static
    {
        $this->ccqTrade = $ccqTrade;

        return $this;
    }

    public function getCcqRegion(): ?CcqRegion
    {
        return $this->ccqRegion;
    }

    public function setCcqRegion(CcqRegion $ccqRegion): static
    {
        $this->ccqRegion = $ccqRegion;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return Collection<int, HoursEntry>
     */
    public function getHoursEntries(): Collection
    {
        return $this->hoursEntries;
    }

    public function addHoursEntry(HoursEntry $hoursEntry): static
    {
        if (!$this->hoursEntries->contains($hoursEntry)) {
            $this->hoursEntries->add($hoursEntry);
            $hoursEntry->setWorker($this);
        }

        return $this;
    }

    public function removeHoursEntry(HoursEntry $hoursEntry): static
    {
        if ($this->hoursEntries->removeElement($hoursEntry)) {
            // set the owning side to null (unless already changed)
            if ($hoursEntry->getWorker() === $this) {
                $hoursEntry->setWorker(null);
            }
        }

        return $this;
    }
}
