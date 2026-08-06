<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Integration\IntegrationTestCase;
use App\Factory\AddressFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Fake\FakeStripeClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class StripeWebhookControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    private const string WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    // ── Authentication ──────────────────────────────────────────────────

    public function test_webhook_is_public_and_returns_200(): void
    {
        $payload = json_encode(['type' => 'unknown.event', 'data' => ['object' => []]]);

        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseStatusCodeSame(200);
    }

    // ── Invalid signature ───────────────────────────────────────────────

    public function test_webhook_invalid_signature_returns_400(): void
    {
        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=9999999999,v1=bogus',
            'CONTENT_TYPE' => 'application/json',
        ], '{"type":"test"}');

        self::assertResponseStatusCodeSame(400);
    }

    // ── Malformed JSON ──────────────────────────────────────────────────

    public function test_webhook_malformed_json_returns_400(): void
    {
        $payload = 'not-json';
        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseStatusCodeSame(400);
    }

    // ── Valid checkout.session.completed ────────────────────────────────

    public function test_checkout_completed_with_valid_signature_activates_subscription(): void
    {
        $orgId = null;
        $org = OrganizationFactory::createOne([
            'stripeCustomerId' => null,
            'subscriptionTier' => null,
            'address' => AddressFactory::new(),
        ]);
        $orgId = (string) $org->getId();
        UserFactory::createOne(['organization' => $org]);

        $event = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_real',
                'mode' => 'subscription',
                'customer' => 'cus_new_123',
                'client_reference_id' => $orgId,
                'metadata' => ['tier' => 'starter'],
            ]],
        ];
        $payload = json_encode($event);

        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseStatusCodeSame(200);

        // Use raw SQL to verify (bypass ORM identity map)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT stripe_customer_id, subscription_tier FROM organization WHERE id = :id',
            ['id' => $orgId],
        );
        self::assertNotFalse($row, 'Organization not found after webhook');
        self::assertSame('cus_new_123', $row['stripe_customer_id'], 'stripeCustomerId was not set');
        self::assertSame('starter', $row['subscription_tier'], 'subscriptionTier was not set');
    }

    // ── Processing error still returns 200 ──────────────────────────────

    public function test_processing_error_returns_500(): void
    {
        $org = OrganizationFactory::createOne([
            'stripeCustomerId' => 'cus_fail',
            'address' => AddressFactory::new(),
        ]);
        UserFactory::createOne(['organization' => $org]);

        /** @var FakeStripeClient $fake */
        $fake = static::getContainer()->get('stripe.client');
        $fake->subscriptionsException = new \RuntimeException('Stripe API down');

        $event = [
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_1',
                'customer' => 'cus_fail',
            ]],
        ];
        $payload = json_encode($event);

        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        // Must return 500 so Stripe retries
        self::assertResponseStatusCodeSame(500);
    }

    // ── Verify constructEvent output ────────────────────────────────────

    public function test_construct_event_output_structure(): void
    {
        $event = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test',
                'mode' => 'subscription',
                'customer' => 'cus_test',
                'client_reference_id' => 'test-uuid',
                'metadata' => ['tier' => 'starter'],
            ]],
        ];
        $payload = json_encode($event);

        $result = \Stripe\Webhook::constructEvent($payload, $this->sign($payload), self::WEBHOOK_SECRET);
        $array = $result->toArray();

        // Verify the structure matches what handleWebhook expects
        self::assertSame('checkout.session.completed', $array['type']);
        self::assertSame('subscription', $array['data']['object']['mode']);
        self::assertSame('cus_test', $array['data']['object']['customer']);
        self::assertSame('test-uuid', $array['data']['object']['client_reference_id']);
        self::assertSame('starter', $array['data']['object']['metadata']['tier']);
    }

    // ── Direct service test ─────────────────────────────────────────────

    public function test_service_directly_updates_organization(): void
    {
        $org = OrganizationFactory::createOne([
            'stripeCustomerId' => null,
            'subscriptionTier' => null,
            'address' => AddressFactory::new(),
        ]);
        $orgId = (string) $org->getId();

        /** @var \App\Service\StripeService $service */
        $service = static::getContainer()->get(\App\Service\StripeService::class);

        $service->handleWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_direct',
                'mode' => 'subscription',
                'customer' => 'cus_direct',
                'client_reference_id' => $orgId,
                'metadata' => ['tier' => 'pro'],
            ]],
        ]);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $conn = $em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT stripe_customer_id, subscription_tier FROM organization WHERE id = :id',
            ['id' => $orgId],
        );
        self::assertNotFalse($row);
        self::assertSame('cus_direct', $row['stripe_customer_id']);
        self::assertSame('pro', $row['subscription_tier']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function sign(string $payload): string
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }
}
