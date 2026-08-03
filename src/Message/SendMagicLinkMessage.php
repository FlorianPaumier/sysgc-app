<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class SendMagicLinkMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context,
    )
    {
    }
}
