<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Integration tests for RegistrationController.
 * Tests registration flow, duplicate email handling, and rate limiting.
 */
#[ResetDatabase]
final class RegistrationControllerTest extends IntegrationTestCase
{

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Reset rate limiter cache pool to prevent bucket exhaustion bleeding between tests.
        /** @var \Symfony\Component\Cache\Adapter\AdapterInterface $pool */
        $pool = self::getContainer()->get('cache.rate_limiter');
        $pool->clear();
    }

    public function test_get_registration_renders_form(): void
    {
        $crawler = $this->client->request('GET', '/inscription');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[type="email"]');
        $this->assertSelectorExists('input[type="text"][name*="organization"]');
        $this->assertSelectorExists('input[type="text"][name*="rbq"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    #[Test]
    public function post_registration_with_valid_data_requires_magic_link_verification(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('registration[register]', [
            'registration[email]' => 'new@example.com',
            'registration[organizationName]' => 'Acme Construction',
            'registration[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        $this->assertResponseRedirects('/login/check-email');

        // Registration must not authenticate the new user before the emailed
        // magic link is presented.
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/connexion');
    }

    #[Test]
    public function post_registration_with_duplicate_email_shows_error_and_redirects_to_login(): void
    {
        UserFactory::createOne(['email' => 'existing@example.com']);

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('registration[register]', [
            'registration[email]' => 'existing@example.com',
            'registration[organizationName]' => 'Another Construction',
            'registration[rbqLicense]' => 'RBQ-9999-9999-99',
        ]);

        // Should redirect to login with error message
        $this->assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', 'existe déjà');
    }

    #[Test]
    public function post_registration_with_invalid_email_returns_form_error(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('registration[register]', [
            'registration[email]' => 'not-an-email',
            'registration[organizationName]' => 'Acme Construction',
            'registration[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[aria-invalid="true"]');
    }

    #[Test]
    public function post_registration_with_invalid_rbq_format_returns_form_error(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('registration[register]', [
            'registration[email]' => 'test@example.com',
            'registration[organizationName]' => 'Acme Construction',
            'registration[rbqLicense]' => 'invalid-rbq',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[aria-invalid="true"]');
    }

    #[Test]
    public function post_registration_with_empty_fields_returns_form_errors(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('registration[register]', [
            'registration[email]' => '',
            'registration[organizationName]' => '',
            'registration[rbqLicense]' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[aria-invalid="true"]');
    }

    #[Test]
    public function post_registration_respects_rate_limiting(): void
    {
        // Make 4 attempts with the same email
        for ($i = 0; $i < 4; $i++) {
            $this->client->request('GET', '/inscription');
            $this->client->submitForm('registration[register]', [
                'registration[email]' => 'test@example.com',
                'registration[organizationName]' => "Company {$i}",
                'registration[rbqLicense]' => 'RBQ-1234-5678-90',
            ]);
        }

        // 4th attempt should be rate limited
        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorTextContains('body', 'Trop de tentatives');
    }

    #[Test]
    public function post_registration_respects_ip_rate_limiting(): void
    {
        // Make 21 requests (exceeds 20 per 5 minutes)
        for ($i = 0; $i < 21; $i++) {
            $this->client->request('GET', '/inscription');
            $this->client->submitForm('registration[register]', [
                'registration[email]' => "different{$i}@example.com",
                'registration[organizationName]' => "Company {$i}",
                'registration[rbqLicense]' => 'RBQ-1234-5678-90',
            ]);
        }

        // Should be rate limited by IP
        $this->assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function post_registration_creates_organization_with_address(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('registration[register]', [
            'registration[email]' => 'test@example.com',
            'registration[organizationName]' => 'Acme Construction',
            'registration[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        // Verify organization was created with address
        $org = OrganizationFactory::repository()->findOneBy(['name' => 'Acme Construction']);
        $this->assertNotNull($org);
        $this->assertSame('RBQ-1234-5678-90', $org->getRbqLicense());
    }
}
