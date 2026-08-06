<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Exception\NotImplementedException;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StripeService
{
    public function __construct(
        private StripeClient $stripe,
        private OrganizationRepository $organizationRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        private string $starterPriceId,
        private string $proPriceId,
        private string $businessPriceId,
    ) {}

    /**
     * Creates a Stripe Checkout Session for subscription and returns the Checkout URL.
     *
     * @return string The URL to redirect the user to Stripe Checkout
     */
    public function createCheckoutSession(Organization $org, string $priceId): string
    {
        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $org->getId(),
            'metadata' => [
                'tier' => $this->tierForPriceId($priceId)?->value ?? '',
            ],
            'automatic_tax' => ['enabled' => true],
            'success_url' => $this->urlGenerator->generate('billing_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->urlGenerator->generate('billing_upgrade', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        if ($org->getStripeCustomerId() !== null) {
            $params['customer'] = $org->getStripeCustomerId();
        } else {
            $firstUser = $org->getUsers()->first();
            if ($firstUser instanceof User) {
                $params['customer_email'] = $firstUser->getEmail();
            }
        }

        $session = $this->stripe->checkout->sessions->create($params);

        return $session->url ?? throw new \RuntimeException('Stripe Checkout session returned no URL');
    }

    /**
     * Dispatches a verified Stripe webhook event.
     *
     * @param array{
     *     type?: string,
     *     data?: array{object?: array<string, mixed>}
     * } $payload The event array from Stripe\Event::toArray()
     */
    public function handleWebhook(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');
        /** @var array<string, mixed> $object */
        $object = (array) ($payload['data']['object'] ?? []);

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            default => $this->logger->info('Evenement Stripe non gere : {type}', ['type' => $type]),
        };
    }

    /**
     * Syncs subscription tier from Stripe for a given customer.
     */
    public function syncSubscription(string $stripeCustomerId): void
    {
        $org = $this->organizationRepository->findOneByStripeCustomerId($stripeCustomerId);
        if ($org === null) {
            $this->logger->warning('Organisation introuvable pour le customer Stripe {customer}', [
                'customer' => $stripeCustomerId,
            ]);

            return;
        }

        $subscriptions = $this->stripe->subscriptions->all([
            'customer' => $stripeCustomerId,
            'status' => 'active',
            'limit' => 1,
            'expand' => ['data.items.data.price'],
        ]);

        if ($subscriptions->data === []) {
            $org->setSubscriptionTier(null);
            $this->entityManager->flush();

            return;
        }

        $subscription = $subscriptions->data[0];

        // Guard against empty items
        if ($subscription->items->data === []) {
            $this->logger->warning('Subscription sans items pour customer {customer}', [
                'customer' => $stripeCustomerId,
            ]);

            return;
        }

        /** @var mixed $item */
        $item = $subscription->items->data[0];
        /** @var mixed $price */
        $price = $item->price ?? null;
        // When expanded, price is a Stripe\Price object; when unexpanded, it's a string ID
        $priceId = \is_string($price) ? $price : ($price['id'] ?? null);

        if ($priceId === null) {
            return;
        }

        $tier = $this->tierForPriceId($priceId);
        if ($tier === null) {
            $this->logger->error('Price ID inconnu dans la subscription Stripe : {priceId}', [
                'priceId' => $priceId,
            ]);

            return;
        }

        $org->setSubscriptionTier($tier);
        $this->entityManager->flush();
    }

    /**
     * Returns the env-configured Stripe price ID for a given subscription tier.
     */
    public function priceIdForTier(SubscriptionTier $tier): string
    {
        return match ($tier) {
            SubscriptionTier::Starter => $this->starterPriceId,
            SubscriptionTier::Pro => $this->proPriceId,
            SubscriptionTier::Business => $this->businessPriceId,
        };
    }

    /**
     * Creates a Stripe Customer Portal session (post-MVP stub).
     *
     * @throws NotImplementedException always — Customer Portal is post-MVP
     */
    public function createCustomerPortal(Organization $org): string
    {
        throw new NotImplementedException('Customer Portal sera disponible prochainement.');
    }

    // ── Private event handlers ──────────────────────────────────────────

    /**
     * @param array<string, mixed> $object
     */
    private function handleCheckoutCompleted(array $object): void
    {
        // Only handle subscription checkouts, not one-time payments
        if (($object['mode'] ?? '') !== 'subscription') {
            return;
        }

        $customerId = $object['customer'] ?? null;
        $clientReferenceId = $object['client_reference_id'] ?? null;

        $org = null;

        // Try by client_reference_id first (org UUID)
        if (\is_string($clientReferenceId)) {
            $org = $this->organizationRepository->find($clientReferenceId);
        }

        // Fallback: try by customer ID (for re-subscriptions)
        if ($org === null && \is_string($customerId)) {
            $org = $this->organizationRepository->findOneByStripeCustomerId($customerId);
        }

        if ($org === null) {
            $this->logger->warning('Organisation introuvable pour checkout.session.completed {id}', [
                'id' => $object['id'] ?? 'unknown',
            ]);

            return;
        }

        /** @var array{tier?: string} $metadata */
        $metadata = $object['metadata'] ?? [];
        $tier = $this->tierFromValue($metadata['tier'] ?? '');
        if (\is_string($customerId)) {
            $org->setStripeCustomerId($customerId);
        }
        if ($tier !== null) {
            $org->setSubscriptionTier($tier);
        }
        $this->entityManager->flush();

        $this->logger->info('Abonnement active : customer={customer}, tier={tier}', [
            'customer' => $customerId,
            'tier' => $tier?->value,
        ]);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function handleInvoicePaid(array $object): void
    {
        $this->logger->info('Facture payee : {id}, montant={amount}', [
            'id' => $object['id'] ?? 'unknown',
            'amount' => $object['amount_paid'] ?? 0,
        ]);

        $customerId = $object['customer'] ?? null;
        if (\is_string($customerId)) {
            $this->syncSubscription($customerId);
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function handleInvoicePaymentFailed(array $object): void
    {
        $this->logger->error('Paiement echoue : {id}, customer={customer}', [
            'id' => $object['id'] ?? 'unknown',
            'customer' => $object['customer'] ?? 'unknown',
        ]);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function handleSubscriptionUpdated(array $object): void
    {
        $customerId = $object['customer'] ?? null;
        if (\is_string($customerId)) {
            $this->syncSubscription($customerId);
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function handleSubscriptionDeleted(array $object): void
    {
        $customerId = $object['customer'] ?? null;
        if (! \is_string($customerId)) {
            return;
        }

        $org = $this->organizationRepository->findOneByStripeCustomerId($customerId);
        if ($org === null) {
            $this->logger->warning('Organisation introuvable pour subscription.deleted : {customer}', [
                'customer' => $customerId,
            ]);

            return;
        }

        // Keep stripeCustomerId for future re-subscription
        $org->setSubscriptionTier(null);
        $this->entityManager->flush();

        $this->logger->info('Abonnement resilie : {customer}', [
            'customer' => $customerId,
        ]);
    }

    // ── Price ID helpers ────────────────────────────────────────────────

    private function tierForPriceId(string $priceId): ?SubscriptionTier
    {
        return match ($priceId) {
            $this->starterPriceId => SubscriptionTier::Starter,
            $this->proPriceId => SubscriptionTier::Pro,
            $this->businessPriceId => SubscriptionTier::Business,
            default => null,
        };
    }

    private function tierFromValue(string $value): ?SubscriptionTier
    {
        return SubscriptionTier::tryFrom($value);
    }
}
