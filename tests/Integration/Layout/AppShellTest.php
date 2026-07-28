<?php

declare(strict_types=1);

namespace App\Tests\Integration\Layout;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies the application shell rendered by templates/layouts/app.html.twig:
 * desktop sidebar (≥lg), mobile bottom nav (<lg, 3 buttons, full-width),
 * the optional {% block aside %} column, and the loading/empty/error state
 * components.
 *
 * Responsive show/hide is asserted structurally (class names) here; the actual
 * computed visibility per breakpoint is covered by e2e/app-shell.spec.ts.
 *
 * @see specs/06-frontend.md, specs/12-ux-ui.md ("Mobile layout")
 */
final class AppShellTest extends WebTestCase
{
    public function testStyleguideRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_styleguide');

        self::assertResponseIsSuccessful();
    }

    public function testDesktopSidebarPresent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_styleguide');

        $sidebar = $crawler->filter('.app-sidebar');
        self::assertSame(1, $sidebar->count(), 'A desktop sidebar (.app-sidebar) must be rendered.');
        self::assertSame('Navigation principale', $sidebar->attr('aria-label'));
        self::assertStringContainsString('Tableau de bord', $sidebar->text());
    }

    public function testMobileBottomNavHasThreeButtons(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_styleguide');

        $bottomNav = $crawler->filter('.bottom-nav');
        self::assertSame(1, $bottomNav->count(), 'A mobile bottom nav (.bottom-nav) must be rendered.');
        self::assertSame('Navigation mobile', $bottomNav->attr('aria-label'));

        $buttons = $bottomNav->filter('.bottom-nav__btn');
        self::assertSame(3, $buttons->count(), 'Bottom nav must have exactly 3 buttons.');

        $labels = $bottomNav->filter('.bottom-nav__label')->each(static fn ($node) => trim($node->text()));
        self::assertSame(['Sécurité', 'Heures', 'Conformité'], $labels);
    }

    public function testMainLandmarkPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_styleguide');

        self::assertSelectorExists('main#main');
    }

    public function testEmptyStateComponentRenders(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_styleguide');

        $empty = $crawler->filter('.empty-state');
        self::assertSame(1, $empty->count());
        // Title text is required so the state is not indicated by color/shape alone (WCAG).
        self::assertStringContainsString('Aucun chantier', $empty->text());
    }

    public function testAlertBannerComponentRendersWithText(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_styleguide');

        $alert = $crawler->filter('.alert-banner');
        self::assertSame(1, $alert->count());
        self::assertSame('alert', $alert->attr('role'));
        // The message must be conveyed as text, not by color alone (WCAG 1.4.1).
        self::assertNotEmpty(trim($alert->text()));
    }

    public function testSpinnerComponentRendersAccessibly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/_styleguide');

        $spinner = $crawler->filter('.spinner[role="status"]');
        // A loading indicator must announce itself to assistive tech (WCAG 4.1.3).
        self::assertGreaterThanOrEqual(1, $spinner->count(), 'Spinner must carry role="status".');
        self::assertSame('Chargement en cours', $spinner->attr('aria-label'));
    }
}
