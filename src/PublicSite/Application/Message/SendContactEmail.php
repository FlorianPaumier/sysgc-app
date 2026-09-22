<?php

declare(strict_types=1);

namespace App\PublicSite\Application\Message;

use App\PublicSite\Application\DTO\ContactDTO;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class SendContactEmail
{
    public function __construct(
        public ContactDTO $contactDTO,
        public string $template,
    ) {}
}
