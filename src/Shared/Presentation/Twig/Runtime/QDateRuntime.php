<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * @see QDateExtension
 */
final class QDateRuntime implements RuntimeExtensionInterface
{
    private const string QUEBEC_DATE_FORMAT = 'd/m/Y';

    /**
     * Format a date as DD/MM/YYYY (Quebec standard).
     *
     * @param \DateTimeInterface|int|string|null $date
     */
    public function format(\DateTimeInterface|int|string|null $date): string
    {
        if (null === $date || '' === $date) {
            return '';
        }

        if (\is_int($date)) {
            $date = new \DateTimeImmutable()->setTimestamp($date);
        }

        if (\is_string($date)) {
            try {
                $date = new \DateTimeImmutable($date);
            } catch (\DateMalformedStringException) {
                /** @var string */
                return $date; // pass through unparseable strings unchanged
            }
        }

        return $date->format(self::QUEBEC_DATE_FORMAT);
    }
}
