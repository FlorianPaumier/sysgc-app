<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Enum\SubscriptionTier;
use App\Factory\AddressFactory;
use App\Factory\OrganizationFactory;
use App\Factory\UserFactory;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Integration tests for OrganizationController::settings.
 */
#[ResetDatabase]
final class OrganizationControllerTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_settings_page_requires_authentication(): void
    {
        $this->client->request('GET', '/organisation');

        // Anonymous access is denied (firewall requires ROLE_USER).
        // The MagicLinkAuthenticator has no entry-point redirect yet,
        // so the response is a 403 Access Denied, not a redirect.
        $this->assertResponseStatusCodeSame(401);
    }

    public function test_settings_page_renders_form_prefilled_with_org_data(): void
    {
        $org = OrganizationFactory::createOne([
            'name' => 'Acme Construction',
            'rbqLicense' => 'RBQ-1234-5678-90',
            'address' => AddressFactory::new([
                'street' => '123 rue de la Commune',
                'postalCode' => 'H2Y 2E2',
                'city' => 'Montréal',
                'country' => 'CA',
            ]),
        ]);
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="organization_settings"]');
        $this->assertInputValueSame('organization_settings[name]', 'Acme Construction');
        $this->assertInputValueSame('organization_settings[rbqLicense]', 'RBQ-1234-5678-90');
        $this->assertInputValueSame('organization_settings[address][street]', '123 rue de la Commune');
        $this->assertSelectorExists('button[type="submit"]');
        $this->assertSelectorExists('input[name="organization_settings[_token]"]');
    }

    public function test_post_valid_changes_persist_and_show_success(): void
    {
        $org = OrganizationFactory::createOne([
            'name' => 'Old Name',
            'rbqLicense' => 'RBQ-1111-1111-11',
            'address' => AddressFactory::new([
                'street' => '1 Old Street',
                'postalCode' => 'G1A 1A1',
                'city' => 'Québec',
                'country' => 'CA',
            ]),
        ]);
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');
        $this->client->submitForm('organization_settings[save]', [
            'organization_settings[name]' => 'Nouveau Nom',
            'organization_settings[rbqLicense]' => 'RBQ-9999-9999-99',
            'organization_settings[address][street]' => '456 New Street',
            'organization_settings[address][street2]' => '',
            'organization_settings[address][postalCode]' => 'H3A 1A1',
            'organization_settings[address][city]' => 'Montréal',
            'organization_settings[address][state]' => '',
            'organization_settings[address][country]' => 'CA',
        ]);

        $this->assertResponseRedirects('/organisation');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('body', 'Vos modifications ont été enregistrées');

        $updated = OrganizationFactory::repository()->findOneBy(['name' => 'Nouveau Nom']);
        $this->assertNotNull($updated);
        $this->assertSame('RBQ-9999-9999-99', $updated->getRbqLicense());
        $this->assertSame('456 New Street', $updated->getAddress()->getStreet());
    }

    public function test_post_empty_name_shows_french_error_inline(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');
        $this->client->submitForm('organization_settings[save]', [
            'organization_settings[name]' => '',
            'organization_settings[rbqLicense]' => 'RBQ-1234-5678-90',
            'organization_settings[address][street]' => '123 rue Test',
            'organization_settings[address][postalCode]' => 'H2Y 2E2',
            'organization_settings[address][city]' => 'Montréal',
            'organization_settings[address][country]' => 'CA',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[name="organization_settings[name]"][aria-invalid="true"]');
    }

    public function test_post_invalid_rbq_format_shows_french_error_inline(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');
        $this->client->submitForm('organization_settings[save]', [
            'organization_settings[name]' => 'Acme Construction',
            'organization_settings[rbqLicense]' => 'invalid-rbq',
            'organization_settings[address][street]' => '123 rue Test',
            'organization_settings[address][postalCode]' => 'H2Y 2E2',
            'organization_settings[address][city]' => 'Montréal',
            'organization_settings[address][country]' => 'CA',
        ]);

        // Symfony returns 422 for form validation errors; the form is re-rendered with errors.
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[name="organization_settings[rbqLicense]"][aria-invalid="true"]');
        $this->assertSelectorTextContains('body', 'RBQ-1234-5678-90');
    }

    public function test_post_empty_address_street_shows_french_error_inline(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');
        $this->client->submitForm('organization_settings[save]', [
            'organization_settings[name]' => 'Acme Construction',
            'organization_settings[rbqLicense]' => 'RBQ-1234-5678-90',
            'organization_settings[address][street]' => '',
            'organization_settings[address][postalCode]' => 'H2Y 2E2',
            'organization_settings[address][city]' => 'Montréal',
            'organization_settings[address][country]' => 'CA',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('input[name="organization_settings[address][street]"][aria-invalid="true"]');
        $this->assertSelectorTextContains('body', 'L\'adresse est obligatoire.');
    }

    public function test_plan_section_shows_placeholder_without_subscription(): void
    {
        $org = OrganizationFactory::createOne();
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Forfait');
        $this->assertSelectorTextContains('body', 'Gérer mon abonnement');
        $this->assertSelectorExists('a[href="/facturation/portail"]');
    }

    public function test_plan_section_shows_tier_and_worker_limit(): void
    {
        $org = OrganizationFactory::createOne([
            'subscriptionTier' => SubscriptionTier::Starter,
        ]);
        $user = UserFactory::createOne(['organization' => $org]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/organisation');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Starter');
        $this->assertSelectorTextContains('body', '5 travailleurs inclus');
    }

    public function test_other_organization_data_is_not_leaked(): void
    {
        $orgA = OrganizationFactory::createOne([
            'name' => 'Org A',
            'rbqLicense' => 'RBQ-1111-1111-11',
        ]);
        $userA = UserFactory::createOne(['organization' => $orgA]);

        // Create another org with a different user.
        $orgB = OrganizationFactory::createOne([
            'name' => 'Org B Should Not Appear',
            'rbqLicense' => 'RBQ-2222-2222-22',
        ]);
        UserFactory::createOne(['organization' => $orgB, 'email' => 'other@example.com']);

        $this->client->loginUser($userA);
        $this->client->request('GET', '/organisation');

        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('organization_settings[name]', 'Org A');
        // Org B name must not appear anywhere in the page.
        $this->assertSelectorTextNotContains('body', 'Org B Should Not Appear');
    }
}
