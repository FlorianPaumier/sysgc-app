<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Resend\Client;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final readonly class ResendMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $from,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array<string, mixed> $context
     *
     * @return void
     *
     * @throws \Throwable
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function send(string $to, string $subject, string $template, array $context): void
    {
        try {
            $this->mailer->send(new TemplatedEmail()->from($this->from)->to($to)->subject($subject)->htmlTemplate($template)->context($context));
        } catch (\Throwable $e) {
            $this->logger->error('Resend API failure', [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'reason' => $e->getMessage(),
            ]);
            throw $e; // re-throw so Messenger retries
        }
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array $context
     * @return void
     * @throws \Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendToAdmin(string $subject, array $context): void
    {
        $this->send($this->from, $subject, "emails/admin_contact.html.twig", $context);
    }
}
