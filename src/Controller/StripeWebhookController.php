<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {}

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signatureHeader = (string) $request->headers->get('stripe-signature', '');

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (UnexpectedValueException | SignatureVerificationException $e) {
            $this->logger->error('Signature webhook Stripe invalide : {message}', [
                'message' => $e->getMessage(),
            ]);

            return new Response('', Response::HTTP_OK);
        }

        try {
            /** @var array{type?: string, data?: array{object?: array<string, mixed>}} $eventArray */
            $eventArray = $event->toArray();
            $this->stripeService->handleWebhook($eventArray);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors du traitement du webhook Stripe : {message}', [
                'message' => $e->getMessage(),
            ]);
        }

        return new Response('', Response::HTTP_OK);
    }
}
