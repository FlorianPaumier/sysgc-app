<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendMagicLinkMessage;
use App\Service\ResendMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsMessageHandler]
final readonly class SendEmailMessageHandler
{
    public function __construct(
        private ResendMailer $mailer,
    ) {}

    /**
     * @throws \Throwable
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
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
