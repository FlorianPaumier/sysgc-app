<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendContactEmail;
use App\Service\ResendMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendContactEmailHandler
{
    public function __construct(
        private ResendMailer $mailer,
    ) {}

    public function __invoke(SendContactEmail $message): void
    {
        $context = [
            'name' => $message->contactDTO->name,
            'target' => $message->contactDTO->email,
            'message' => $message->contactDTO->message,
            'subject' => $message->contactDTO->subject,
        ];

        // Notify admin.
        $this->mailer->sendToAdmin(
            $message->contactDTO->subject,
            [
                'subject' => $message->contactDTO->subject,
                'name' => $message->contactDTO->name,
                'message' => $message->contactDTO->message,
                'from' => $message->contactDTO->email,
            ],
        );

        // Send auto-response confirmation to the user.
        $this->mailer->send(
            $message->contactDTO->email,
            $message->contactDTO->subject,
            'emails/contact_admin.html.twig',
            $context,
        );
    }
}
