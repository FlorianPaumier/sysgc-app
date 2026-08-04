<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Factory\OrganizationFactory;
use App\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for RegistrationController.
 * Tests registration flow, duplicate email handling, and rate limiting.
 */
final class RegistrationControllerTest extends IntegrationTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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

    /**
     * @test
     */
    public function post_registration_with_valid_data_creates_org_and_user_and_redirects(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('register[register]', [
            'register[email]' => 'new@example.com',
            'register[organizationName]' => 'Acme Construction',
            'register[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        // Should auto-login and redirect (need dashboard route)
        $this->assertResponseIsSuccessful();
    }

    /**
     * @test
     */
    public function post_registration_with_duplicate_email_shows_error_and_redirects_to_login(): void
    {
        UserFactory::createOne(['email' => 'existing@example.com']);

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('register[register]', [
            'register[email]' => 'existing@example.com',
            'register[organizationName]' => 'Another Construction',
            'register[rbqLicense]' => 'RBQ-9999-9999-99',
        ]);

        // Should redirect to login with error message
        $this->assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', 'existe déjà');
    }

    /**
     * @test
     */
    public function post_registration_with_invalid_email_returns_form_error(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('register[register]', [
            'register[email]' => 'not-an-email',
            'register[organizationName]' => 'Acme Construction',
            'register[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[type="email"].is-invalid');
    }

    /**
     * @test
     */
    public function post_registration_with_invalid_rbq_format_returns_form_error(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('register[register]', [
            'register[email]' => 'test@example.com',
            'register[organizationName]' => 'Acme Construction',
            'register[rbqLicense]' => 'invalid-rbq',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name*="rbq"].is-invalid');
    }

    /**
     * @test
     */
    public function post_registration_with_empty_fields_returns_form_errors(): void
    {
        $this->client->request('GET', '/inscription');
        $crawler = $this->client->submitForm('register[register]', [
            'register[email]' => '',
            'register[organizationName]' => '',
            'register[rbqLicense]' => '',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[type="email"].is-invalid');
        $this->assertSelectorExists('input[name*="organization"].is-invalid');
        $this->assertSelectorExists('input[name*="rbq"].is-invalid');
    }

    /**
     * @test
     */
    public function post_registration_respects_rate_limiting(): void
    {
        // Make 4 attempts with the same email
        for ($i = 0; $i < 4; $i++) {
            $this->client->request('GET', '/inscription');
            $this->client->submitForm('register[register]', [
                'register[email]' => 'test@example.com',
                'register[organizationName]' => "Company {$i}",
                'register[rbqLicense]' => 'RBQ-1234-5678-90',
            ]);
        }

        // 4th attempt should be rate limited
        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorTextContains('body', 'Trop de tentatives');
    }

    /**
     * @test
     */
    public function post_registration_respects_ip_rate_limiting(): void
    {
        // Make 21 requests (exceeds 20 per 5 minutes)
        for ($i = 0; $i < 21; $i++) {
            $this->client->request('GET', '/inscription');
            $this->client->submitForm('register[register]', [
                'register[email]' => "different{$i}@example.com",
                'register[organizationName]' => "Company {$i}",
                'register[rbqLicense]' => 'RBQ-1234-5678-90',
            ]);
        }

        // Should be rate limited by IP
        $this->assertResponseStatusCodeSame(429);
    }

    /**
     * @test
     */
    public function post_registration_creates_organization_with_address(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('register[register]', [
            'register[email]' => 'test@example.com',
            'register[organizationName]' => 'Acme Construction',
            'register[rbqLicense]' => 'RBQ-1234-5678-90',
        ]);

        // Verify organization was created with address
        $org = OrganizationFactory::repository()->findOneBy(['name' => 'Acme Construction']);
        $this->assertNotNull($org);
        $this->assertSame('RBQ-1234-5678-90', $org->getRbqLicense());
    }
}
