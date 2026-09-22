<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Identity\Domain\Enum\UserRole;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Smoke tests for the public home page — proves the base template renders,
 * the design-system stylesheet is wired, and the semantic main landmark
 * (skip-link target) is present.
 *
 * @see specs/12-ux-ui.md (design tokens), specs/06-frontend.md (base template)
 */
#[ResetDatabase]
final class HomeControllerTest extends WebTestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('publicRouteProvider')]
    public function testPublicRoutesStayOnThePublicFrontend(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-frontend="public"]');
        self::assertSelectorNotExists('[data-app-chrome]');
    }

    /** @return iterable<string, array{string}> */
    public static function publicRouteProvider(): iterable
    {
        yield 'home' => ['/'];
        yield 'about' => ['/a-propos'];
        yield 'contact' => ['/contact'];
        yield 'login' => ['/connexion'];
        yield 'registration' => ['/inscription'];
    }

    public function testHomePageRendersWithBaseLayout(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testHomePageHasSemanticMainLandmark(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        // The skip link points here — required for WCAG 2.4.1.
        self::assertSelectorExists('main#main');
    }

    public function testHomePageWiresStylesheet(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $links = $crawler->filter('head link[rel="stylesheet"]')
            ->each(
                static fn ($node) => $node->attr('href') ?? '',
            );

        self::assertTrue(
            [] !== \array_filter(
                $links,
                static fn (string $href) => str_contains($href, 'styles/public-') && str_ends_with($href, '.css'),
            ),
            'The public frontend stylesheet must be linked in <head>.',
        );
    }

    public function testPublicPageOnlyLoadsPublicFrontend(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSelectorExists('body[data-frontend="public"]');
        self::assertSelectorExists('[data-public-chrome]');
        self::assertSelectorNotExists('[data-app-chrome]');

        self::assertSelectorExists('link[rel="stylesheet"][href*="styles/public-"]');
        self::assertSelectorNotExists('link[rel="stylesheet"][href*="styles/authenticated-"]');
        self::assertSelectorTextContains('script[type="module"]', "import 'public'");
        self::assertSelectorTextNotContains('script[type="module"]', "import 'authenticated'");
    }

    public function testHomePageLangIsFrench(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('authenticatedHomeProvider')]
    public function testPublicNavigationReturnsAuthenticatedUsersToTheirRoleHome(string $role, string $expectedHref): void
    {
        $client = static::createClient();
        $organization = OrganizationFactory::createOne(['address' => AddressFactory::new()]);
        $user = UserFactory::createOne([
            'organization' => $organization,
            'roles' => [$role],
        ]);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('.nav-actions a.nav-cta[href="%s"]', $expectedHref));
        self::assertSelectorTextContains('.nav-actions', 'Ouvrir mon espace');
        self::assertSelectorNotExists('.nav-actions a[href="/connexion"]');
    }

    /** @return iterable<string, array{string, string}> */
    public static function authenticatedHomeProvider(): iterable
    {
        yield 'owner' => [UserRole::OWNER->value, '/dashboard'];
        yield 'foreman' => [UserRole::FOREMAN->value, '/dashboard/terrain'];
    }
}
