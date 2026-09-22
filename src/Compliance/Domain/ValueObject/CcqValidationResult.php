<?php

declare(strict_types=1);

namespace App\Compliance\Domain\ValueObject;

/**
 * Result of CCQ validation. Contains all errors/warnings found.
 *
 * $valid is true only when there are zero error-severity issues (warnings allowed).
 */
readonly class CcqValidationResult
{
    /**
     * @param CcqError[] $errors
     */
    public function __construct(
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return array_all($this->errors, fn ($error) => ! $error->isError());
    }

    /**
     * @return CcqError[]
     */
    public function getErrors(): array
    {
        return array_filter($this->errors, fn (CcqError $e): bool => $e->isError());
    }

    /**
     * @return CcqError[]
     */
    public function getWarnings(): array
    {
        return array_filter($this->errors, fn (CcqError $e): bool => $e->isWarning);
    }

    public function hasErrors(): bool
    {
        return count($this->getErrors()) > 0;
    }

    public function hasWarnings(): bool
    {
        return count($this->getWarnings()) > 0;
    }
}
