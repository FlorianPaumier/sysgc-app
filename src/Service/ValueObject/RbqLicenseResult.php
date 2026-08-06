<?php

declare(strict_types=1);

namespace App\Service\ValueObject;

/**
 * RBQ license verification result.
 */
readonly class RbqLicenseResult
{
    /**
     * @param bool $found Whether license exists in RBQ registry
     * @param string|null $businessName Business name from registry
     * @param string|null $category License category (e.g., "Entrepreneur général")
     * @param \DateTime|null $expiryDate License expiration date
     * @param string|null $status License status: "valid", "expired", or "suspended"
     * @param string|null $error Error code if verification failed: "parse_error", "timeout", "network_error"
     */
    public function __construct(
        public bool $found = false,
        public ?string $businessName = null,
        public ?string $category = null,
        public ?\DateTime $expiryDate = null,
        public ?string $status = null,
        public ?string $error = null,
    ) {}
}
