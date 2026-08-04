<?php

namespace App\MessageHandler;

use App\Message\SendContactEmail;
use App\Service\ResendMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendContactEmailHandler
{
    public function __construct(private ResendMailer $mailer)
    {
    }

    public function __invoke(SendContactEmail $message): void
    {
        $this->mailer->send(
            $message->contactDTO->email,
            $message->contactDTO->subject,
            $message->template,
            ["name" => $message->contactDTO->name, "message" => $message->contactDTO->message],
        );

        $this->mailer->sendToAdmin(
            $message->contactDTO->subject,
            ["name" => $message->contactDTO->name, "message" => $message->contactDTO->message, "from" => $message->contactDTO->email ],
        );
    }
}
