<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Enum\SubscriptionTier;
use App\Service\StripeService;
use App\Tests\Integration\IntegrationTestCase;
use App\Factory\AddressFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Fake\FakeStripeClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class BillingControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    private function loginUser(): void
    {
        $org = OrganizationFactory::createOne([
            'address' => AddressFactory::new(),
        ]);
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);
    }

    // ── Authentication ──────────────────────────────────────────────────

    public function test_upgrade_page_requires_authentication(): void
    {
        $this->client->request('GET', '/forfaits');
        self::assertResponseStatusCodeSame(401);
    }

    // ── Upgrade page ────────────────────────────────────────────────────

    public function test_upgrade_page_shows_three_pricing_cards(): void
    {
        $this->loginUser();
        $this->client->request('GET', '/forfaits');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Starter');
        self::assertSelectorTextContains('body', 'Pro');
        self::assertSelectorTextContains('body', 'Business');
        self::assertSelectorTextContains('body', '79');
        self::assertSelectorTextContains('body', '149');
        self::assertSelectorTextContains('body', '199');
        self::assertSelectorTextContains('body', '5 travailleurs');
        self::assertSelectorTextContains('body', '20 travailleurs');
        self::assertSelectorTextContains('body', '50 travailleurs');
        self::assertSelectorExists('input[name="billing_plan[_token]"]');
    }

    public function test_upgrade_page_shows_current_tier_when_subscribed(): void
    {
        $org = OrganizationFactory::createOne([
            'subscriptionTier' => SubscriptionTier::Pro,
            'address' => AddressFactory::new(),
        ]);
        $user = UserFactory::createOne(['organization' => $org]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/forfaits');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Forfait actuel');
    }

    // ── Checkout POST ───────────────────────────────────────────────────

    public function test_post_tier_redirects_to_stripe(): void
    {
        $this->loginUser();
        $this->client->disableReboot();

        $this->client->request('GET', '/forfaits');
        $crawler = $this->client->getCrawler();
        $token = $crawler->filter('input[name="billing_plan[_token]"]')->attr('value');

        $this->client->request('POST', '/forfaits', [
            'billing_plan' => [
                'tier' => 'pro',
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects();

        /** @var FakeStripeClient $fake */
        $fake = static::getContainer()->get('stripe.client');
        self::assertSame('subscription', $fake->checkoutCreateParams['mode']);
        self::assertSame('price_pro', $fake->checkoutCreateParams['line_items'][0]['price']);
    }

    public function test_post_invalid_tier_shows_error(): void
    {
        $this->loginUser();

        $this->client->request('GET', '/forfaits');
        $crawler = $this->client->getCrawler();
        $token = $crawler->filter('input[name="billing_plan[_token]"]')->attr('value');

        $this->client->request('POST', '/forfaits', [
            'billing_plan' => [
                'tier' => 'enterprise',
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_post_without_csrf_token_is_rejected(): void
    {
        $this->loginUser();

        $this->client->request('POST', '/forfaits', [
            'billing_plan' => [
                'tier' => 'pro',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_post_checkout_failure_flashes_and_redirects(): void
    {
        $this->loginUser();

        // Prevent kernel reboot so fake retains its state across requests
        $this->client->disableReboot();

        /** @var FakeStripeClient $fake */
        $fake = static::getContainer()->get('stripe.client');
        $fake->checkoutException = new \RuntimeException('Stripe API error');

        $this->client->request('GET', '/forfaits');
        $crawler = $this->client->getCrawler();
        $token = $crawler->filter('input[name="billing_plan[_token]"]')->attr('value');

        $this->client->request('POST', '/forfaits', [
            'billing_plan' => [
                'tier' => 'pro',
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/forfaits');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'paiement n\'a pas pu');
    }

    // ── Portal stub ─────────────────────────────────────────────────────

    public function test_portal_route_requires_auth(): void
    {
        $this->client->request('GET', '/facturation/portail');
        self::assertResponseStatusCodeSame(401);
    }

    public function test_portal_route_redirects_with_flash(): void
    {
        $this->loginUser();

        $this->client->request('GET', '/facturation/portail');

        self::assertResponseRedirects('/forfaits');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'bientot disponible');
    }

    // ── Success page ────────────────────────────────────────────────────

    public function test_success_page_requires_auth(): void
    {
        $this->client->request('GET', '/facturation/succes');
        self::assertResponseStatusCodeSame(401);
    }

    public function test_success_page_shows_thank_you(): void
    {
        $this->loginUser();

        $this->client->request('GET', '/facturation/succes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Merci');
        self::assertSelectorTextContains('body', 'abonnement est actif');
    }
}
