<?php

namespace App\MessageHandler;

use App\Message\SendMagicLinkMessage;
use App\Service\ResendMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendEmailMessageHandler
{
    public function __construct(private ResendMailer $mailer) {}

    public function __invoke(SendMagicLinkMessage $message): void
    {
        $this->mailer->send(
            $message->to,
            $message->subject,
            $message->template,
            $message->context,
        );
    }
}
