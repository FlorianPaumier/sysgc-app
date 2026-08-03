<?php
declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Resend\Client;
use Twig\Environment;

final readonly class ResendMailer
{

    public function __construct(
        private readonly Client $resend,
        private readonly string $from,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function send(string $to, string $subject, string $template, array $context): void
    {
        $html = $this->twig->render($template, $context);

        try {
            $this->resend->emails->send([
                'from'    => $this->from,
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Resend API failure', [
                'to'       => $to,
                'subject'  => $subject,
                'template' => $template,
                'reason'   => $e->getMessage(),
            ]);
            throw $e; // re-throw so Messenger retries
        }
    }
}
