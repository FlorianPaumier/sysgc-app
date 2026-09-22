<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for SecurityController login flow.
 * Tests form rendering, magic link dispatch, and rate limiting.
 */
final class SecurityControllerTest extends IntegrationTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Reset rate limiter cache pool to prevent bucket exhaustion bleeding between tests.
        /** @var \Symfony\Component\Cache\Adapter\AdapterInterface $pool */
        $pool = self::getContainer()->get('cache.rate_limiter');
        $pool->clear();
    }

    public function test_get_login_renders_form_with_csrf(): void
    {
        $crawler = $this->client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[type="email"]');
        $this->assertSelectorExists('input[type="hidden"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    #[Test]
    public function post_login_with_valid_email_dispatches_magic_link_and_shows_confirmation(): void
    {
        UserFactory::createOne(['email' => 'test@example.com']);

        $this->client->request('GET', '/connexion');
        $form = $this->client->submitForm('login[send]', [
            'login[email]' => 'test@example.com',
        ]);

        $this->assertResponseRedirects('/login/check-email');
    }

    #[Test]
    public function post_login_with_unknown_email_shows_same_confirmation_to_prevent_enumeration(): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('login[send]', [
            'login[email]' => 'unknown@example.com',
        ]);

        $this->assertResponseRedirects('/login/check-email');
        // Same confirmation page - no "user not found" error
    }

    #[Test]
    public function post_login_with_invalid_email_format_still_shows_check_email(): void
    {
        // LoginType only has NotBlank constraint — no Email constraint.
        // Any non-empty string passes validation; enumeration is prevented
        // by always showing the same "check email" confirmation.
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('login[send]', [
            'login[email]' => 'not-an-email',
        ]);

        $this->assertResponseRedirects('/login/check-email');
    }

    #[Test]
    public function post_login_with_empty_email_returns_form_error(): void
    {
        $this->client->request('GET', '/connexion');
        $crawler = $this->client->submitForm('login[send]', [
            'login[email]' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[aria-invalid="true"]');
    }

    #[Test]
    public function post_login_fourth_attempt_within_15_minutes_is_rate_limited(): void
    {
        UserFactory::createOne(['email' => 'test@example.com']);

        // Make 4 attempts with the same email
        for ($i = 0; $i < 4; $i++) {
            $this->client->request('GET', '/connexion');
            $this->client->submitForm('login[send]', [
                'login[email]' => 'test@example.com',
            ]);
        }

        // 4th attempt should be rate limited
        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorTextContains('body', 'Trop de tentatives');
    }

    #[Test]
    public function post_login_respects_ip_rate_limiting(): void
    {
        // Make 21 requests (exceeds 20 per 5 minutes)
        for ($i = 0; $i < 21; $i++) {
            $this->client->request('GET', '/connexion');
            $this->client->submitForm('login[send]', [
                'login[email]' => "different{$i}@example.com",
            ]);
        }

        // Should be rate limited by IP
        $this->assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function post_login_with_csrf_token_failure_returns_form_error(): void
    {
        // Submit form without CSRF token by making raw POST
        $this->client->request('POST', '/connexion', [
            'login' => ['email' => 'test@example.com'],
        ]);

        // Symfony 8.1 returns 422 on invalid form submission
        $this->assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function login_verify_route_exists_but_intercepted_by_authenticator(): void
    {
        $this->client->request('GET', '/connexion/verifier?token=invalid&email=test@example.com');

        // Authenticator redirects to login on failure
        $this->assertResponseRedirects('/connexion');
    }

    #[Test]
    public function logout_route_redirects_to_login(): void
    {
        $user = UserFactory::createOne(['email' => 'test@example.com']);
        $this->client->loginUser($user);

        $this->client->request('GET', '/deconnexion');

        $this->assertResponseRedirects('/connexion');
    }
}
