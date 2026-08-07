<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for the public home page — proves the base template renders,
 * the design-system stylesheet is wired, and the semantic main landmark
 * (skip-link target) is present.
 *
 * @see specs/12-ux-ui.md (design tokens), specs/06-frontend.md (base template)
 */
final class HomeControllerTest extends WebTestCase
{
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
                static fn (string $href) => str_contains($href, 'styles/app') && str_ends_with($href, '.css'),
            ),
            'The compiled Tailwind stylesheet (styles/global.css) must be linked in <head>.',
        );
    }

    public function testHomePageLangIsFrench(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
    }
}
