<?php

namespace App\MessageHandler;

use App\Message\SendContactEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendContactEmailHandler
{
    public function __invoke(SendContactEmail $message): void
    {
        // do something with your message
    }
}
