<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class SendMagicLinkMessage
{
    /**
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context,
    ) {}
}
