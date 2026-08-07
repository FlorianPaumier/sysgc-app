<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Exception\NotImplementedException;
use App\Repository\OrganizationRepository;
use App\Service\StripeService;
use App\Tests\Fake\FakeStripeClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(StripeService::class)]
#[AllowMockObjectsWithoutExpectations]
final class StripeServiceTest extends TestCase
{
    private FakeStripeClient $stripe;
    /** @var OrganizationRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repo;
    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $em;
    /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;
    /** @var UrlGeneratorInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $urlGenerator;
    private StripeService $service;

    private const string STARTER_PRICE_ID = 'price_starter';
    private const string PRO_PRICE_ID = 'price_pro';
    private const string BUSINESS_PRICE_ID = 'price_business';

    protected function setUp(): void
    {
        $this->stripe = new FakeStripeClient();
        $this->repo = $this->createMock(OrganizationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $this->urlGenerator->method('generate')
            ->willReturnCallback(fn (string $route) => match ($route) {
                'billing_success' => 'http://localhost/facturation/succes',
                'billing_upgrade' => 'http://localhost/forfaits',
                default => throw new \RuntimeException("Unexpected route: {$route}"),
            });

        $this->service = new StripeService(
            $this->stripe,
            $this->repo,
            $this->em,
            $this->logger,
            $this->urlGenerator,
            self::STARTER_PRICE_ID,
            self::PRO_PRICE_ID,
            self::BUSINESS_PRICE_ID,
        );
    }

    private function createOrganization(?string $stripeCustomerId = null, ?SubscriptionTier $tier = null): Organization
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setRbqLicense('RBQ-1234-5678-90');
        if ($stripeCustomerId !== null) {
            $org->setStripeCustomerId($stripeCustomerId);
        }
        if ($tier !== null) {
            $org->setSubscriptionTier($tier);
        }

        return $org;
    }

    // ── createCheckoutSession ──────────────────────────────────────────

    public function test_create_checkout_session_returns_url(): void
    {
        $org = $this->createOrganization();

        $url = $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_abc', $url);
    }

    public function test_create_checkout_session_posts_subscription_mode(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertSame('subscription', $this->stripe->checkoutCreateParams['mode']);
    }

    public function test_create_checkout_session_passes_correct_price_id(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        $lineItems = $this->stripe->checkoutCreateParams['line_items'];
        self::assertCount(1, $lineItems);
        self::assertSame('price_pro', $lineItems[0]['price']);
        self::assertSame(1, $lineItems[0]['quantity']);
    }

    public function test_create_checkout_session_sets_client_reference_id(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::STARTER_PRICE_ID);

        self::assertSame((string) $org->getId(), $this->stripe->checkoutCreateParams['client_reference_id']);
    }

    public function test_create_checkout_session_sets_tier_metadata(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertSame('pro', $this->stripe->checkoutCreateParams['metadata']['tier']);
    }

    public function test_create_checkout_session_enables_automatic_tax(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertTrue($this->stripe->checkoutCreateParams['automatic_tax']['enabled']);
    }

    public function test_create_checkout_session_reuses_existing_customer(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_existing');

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertSame('cus_existing', $this->stripe->checkoutCreateParams['customer']);
        self::assertArrayNotHasKey('customer_email', $this->stripe->checkoutCreateParams);
    }

    public function test_create_checkout_session_uses_customer_email_when_no_stripe_id(): void
    {
        $org = $this->createOrganization();
        $user = new User()->setEmail('owner@example.com');
        $org->addUser($user);

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertArrayNotHasKey('customer', $this->stripe->checkoutCreateParams);
        self::assertSame('owner@example.com', $this->stripe->checkoutCreateParams['customer_email']);
    }

    public function test_create_checkout_session_sets_success_and_cancel_urls(): void
    {
        $org = $this->createOrganization();

        $this->service->createCheckoutSession($org, self::PRO_PRICE_ID);

        self::assertStringContainsString('facturation/succes', $this->stripe->checkoutCreateParams['success_url']);
        self::assertStringContainsString('forfaits', $this->stripe->checkoutCreateParams['cancel_url']);
    }

    // ── handleWebhook: checkout.session.completed ───────────────────────

    public function test_handle_webhook_checkout_completed_activates_subscription(): void
    {
        $org = $this->createOrganization();
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')
            ->with('cus_new')
            ->willReturn($org);

        $this->em->expects($this->once())->method('flush');

        $this->service->handleWebhook($this->checkoutCompletedPayload(
            customer: 'cus_new',
            clientReferenceId: (string) $org->getId(),
            tier: 'starter',
        ));

        self::assertSame('cus_new', $org->getStripeCustomerId());
        self::assertSame(SubscriptionTier::Starter, $org->getSubscriptionTier());
    }

    public function test_handle_webhook_checkout_completed_finds_org_by_client_reference_id(): void
    {
        $org = $this->createOrganization();
        // org has no stripeCustomerId yet — find by client_reference_id first
        $this->repo->expects($this->once())
            ->method('find')
            ->with($org->getId())
            ->willReturn($org);

        $this->em->expects($this->once())->method('flush');

        $this->service->handleWebhook($this->checkoutCompletedPayload(
            customer: 'cus_new',
            clientReferenceId: (string) $org->getId(),
            tier: 'pro',
        ));

        self::assertSame('cus_new', $org->getStripeCustomerId());
        self::assertSame(SubscriptionTier::Pro, $org->getSubscriptionTier());
    }

    public function test_handle_webhook_checkout_completed_payment_mode_is_ignored(): void
    {
        $this->em->expects($this->never())->method('flush');

        $this->service->handleWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test',
                'mode' => 'payment',
                'customer' => 'cus_x',
                'client_reference_id' => null,
                'metadata' => ['tier' => 'starter'],
            ]],
        ]);
    }

    public function test_handle_webhook_checkout_completed_unknown_org_is_logged(): void
    {
        $this->repo->method('find')->willReturn(null);
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('Organisation introuvable'));

        $this->em->expects($this->never())->method('flush');

        $this->service->handleWebhook($this->checkoutCompletedPayload(
            customer: 'cus_unknown',
            clientReferenceId: '00000000-0000-0000-0000-000000000000',
            tier: 'starter',
        ));
    }

    // ── handleWebhook: invoice.paid ─────────────────────────────────────

    public function test_handle_webhook_invoice_paid_logs_and_syncs(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_123', tier: SubscriptionTier::Pro);
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_123')->willReturn($org);

        // syncSubscription fetches from Stripe — return Business tier
        $this->stripe->subscriptionListData = [
            ['id' => 'sub_active', 'status' => 'active', 'items' => ['data' => [['price' => ['id' => 'price_business']]]]],
        ];

        $this->logger->expects($this->once())->method('info')
            ->with($this->stringContains('Facture payee'));

        $this->service->handleWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_123',
                'customer' => 'cus_123',
                'amount_paid' => 14900,
            ]],
        ]);

        self::assertSame(SubscriptionTier::Business, $org->getSubscriptionTier());
    }

    // ── handleWebhook: invoice.payment_failed ───────────────────────────

    public function test_handle_webhook_invoice_payment_failed_logs_error(): void
    {
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('Paiement echoue'));

        $this->em->expects($this->never())->method('flush');

        $this->service->handleWebhook([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_456',
                'customer' => 'cus_123',
            ]],
        ]);
    }

    // ── handleWebhook: customer.subscription.updated ────────────────────

    public function test_handle_webhook_subscription_updated_syncs_tier(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_abc', tier: SubscriptionTier::Starter);
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_abc')->willReturn($org);

        $this->stripe->subscriptionListData = [
            ['id' => 'sub_1', 'status' => 'active', 'items' => ['data' => [['price' => ['id' => 'price_business']]]]],
        ];

        $this->service->handleWebhook([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_1',
                'customer' => 'cus_abc',
            ]],
        ]);

        self::assertSame(SubscriptionTier::Business, $org->getSubscriptionTier());
    }

    // ── handleWebhook: customer.subscription.deleted ────────────────────

    public function test_handle_webhook_subscription_deleted_downgrades_tier(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_abc', tier: SubscriptionTier::Pro);
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_abc')->willReturn($org);

        $this->em->expects($this->once())->method('flush');

        $this->service->handleWebhook([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_1',
                'customer' => 'cus_abc',
            ]],
        ]);

        self::assertNull($org->getSubscriptionTier());
        self::assertSame('cus_abc', $org->getStripeCustomerId());
    }

    // ── syncSubscription ────────────────────────────────────────────────

    public function test_sync_subscription_no_active_subscription_downgrades(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_x', tier: SubscriptionTier::Pro);
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_x')->willReturn($org);

        $this->stripe->subscriptionListData = []; // no active subs

        $this->service->syncSubscription('cus_x');

        self::assertNull($org->getSubscriptionTier());
    }

    public function test_sync_subscription_unknown_customer_logs_warning(): void
    {
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_x')->willReturn(null);

        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('Organisation introuvable'));

        $this->service->syncSubscription('cus_x');
    }

    public function test_sync_subscription_unknown_price_leaves_tier_unchanged(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_x', tier: SubscriptionTier::Pro);
        $this->repo->expects($this->once())->method('findOneByStripeCustomerId')->with('cus_x')->willReturn($org);

        $this->stripe->subscriptionListData = [
            ['id' => 'sub_1', 'status' => 'active', 'items' => ['data' => [['price' => ['id' => 'price_unknown']]]]],
        ];

        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('Price ID inconnu'));

        $this->service->syncSubscription('cus_x');

        self::assertSame(SubscriptionTier::Pro, $org->getSubscriptionTier());
    }

    // ── unknown event ───────────────────────────────────────────────────

    public function test_handle_webhook_unknown_event_type_is_logged(): void
    {
        $this->logger->expects($this->once())->method('info')
            ->with($this->stringContains('Evenement Stripe non gere'));

        $this->service->handleWebhook([
            'type' => 'charge.succeeded',
            'data' => ['object' => ['id' => 'ch_123']],
        ]);
    }

    // ── createCustomerPortal ────────────────────────────────────────────

    public function test_create_customer_portal_throws_not_implemented(): void
    {
        $org = $this->createOrganization(stripeCustomerId: 'cus_123');

        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('Customer Portal sera disponible prochainement.');

        $this->service->createCustomerPortal($org);
    }

    // ── priceIdForTier ──────────────────────────────────────────────────

    public function test_price_id_for_tier_starter(): void
    {
        self::assertSame('price_starter', $this->service->priceIdForTier(SubscriptionTier::Starter));
    }

    public function test_price_id_for_tier_pro(): void
    {
        self::assertSame('price_pro', $this->service->priceIdForTier(SubscriptionTier::Pro));
    }

    public function test_price_id_for_tier_business(): void
    {
        self::assertSame('price_business', $this->service->priceIdForTier(SubscriptionTier::Business));
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function checkoutCompletedPayload(string $customer, string $clientReferenceId, string $tier): array
    {
        return [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'mode' => 'subscription',
                'customer' => $customer,
                'client_reference_id' => $clientReferenceId,
                'metadata' => ['tier' => $tier],
            ]],
        ];
    }
}
