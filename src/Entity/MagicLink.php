<?php

namespace App\Entity;

use App\Repository\MagicLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MagicLinkRepository::class)]
class MagicLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $magicLinkToken = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $magicLinkTokenExpiresAt = null;

    #[ORM\ManyToOne(inversedBy: 'magicLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMagicLinkToken(): ?string
    {
        return $this->magicLinkToken;
    }

    public function setMagicLinkToken(string $magicLinkToken): static
    {
        $this->magicLinkToken = $magicLinkToken;

        return $this;
    }

    public function getMagicLinkTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->magicLinkTokenExpiresAt;
    }

    public function setMagicLinkTokenExpiresAt(\DateTimeImmutable $magicLinkTokenExpiresAt): static
    {
        $this->magicLinkTokenExpiresAt = $magicLinkTokenExpiresAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
