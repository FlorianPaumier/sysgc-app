<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Resend\Client;
use Twig\Environment;

final readonly class ResendMailer
{
    public function __construct(
        private Client $resend,
        private string $from,
        private Environment $twig,
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
            $html = $this->twig->render($template, $context);
            $this->resend->emails->send([
                'from' => $this->from,
                'to' => [$to],
                'subject' => $subject,
                'html' => $html,
            ]);
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
}
