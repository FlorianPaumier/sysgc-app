<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MagicLinkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single-use passwordless login token.
 *
 * The raw 128-char hex token travels in the email URL; only its SHA-256 hash
 * (64 hex chars) is persisted here. Expiry is 15 minutes.
 *
 * @see specs/04-auth.md
 */
#[ORM\Entity(repositoryClass: MagicLinkRepository::class)]
class MagicLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * hash('sha256', $rawToken) — never the raw token.
     */
    #[ORM\Column(length: 64)]
    private ?string $tokenHash = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $magicLinkTokenExpiresAt = null;

    #[ORM\ManyToOne(inversedBy: 'magicLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(#[\SensitiveParameter] string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

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

    /**
     * Whether the link is still within its 15-minute validity window.
     */
    public function isValid(\DateTimeImmutable $now): bool
    {
        return $this->magicLinkTokenExpiresAt !== null && $this->magicLinkTokenExpiresAt > $now;
    }
}
