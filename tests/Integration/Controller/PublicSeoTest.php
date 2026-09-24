<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicSeoTest extends WebTestCase
{
    public function testCanonicalAndOpenGraphUrlsUseHttpsAndTheCurrentPathBehindAProxy(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => 'www.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $client->request('GET', '/a-propos?campaign=audit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href="https://www.example.test/a-propos"]');
        self::assertSelectorExists('meta[property="og:url"][content="https://www.example.test/a-propos"]');
        self::assertSelectorExists('meta[property="og:image"][content^="https://www.example.test/"]');
    }

    #[DataProvider('nonIndexablePageProvider')]
    public function testAuthenticationPagesAreNotIndexable(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex,nofollow"]');
    }

    /** @return iterable<string, array{string}> */
    public static function nonIndexablePageProvider(): iterable
    {
        yield 'login' => ['/connexion'];
        yield 'registration' => ['/inscription'];
        yield 'check email' => ['/login/check-email'];
    }

    public function testBaseTemplateExposesCustomizableSeoBlocks(): void
    {
        $source = file_get_contents(self::projectFile('templates/base.html.twig'));

        self::assertIsString($source);
        self::assertStringContainsString('{% block meta_description %}', $source);
        self::assertStringContainsString('{% block meta_robots %}', $source);
        self::assertStringContainsString('{% block canonical_url %}', $source);
        self::assertStringContainsString('{% block og_description %}', $source);
        self::assertStringContainsString('{% block og_url %}', $source);
        self::assertStringContainsString('{% block og_image %}', $source);
    }

    public function testRobotsReferencesTheHttpsSitemap(): void
    {
        $robots = file_get_contents(self::projectFile('public/robots.txt'));

        self::assertIsString($robots, 'public/robots.txt must exist so the web server can return it directly.');
        self::assertMatchesRegularExpression('/^User-agent: \*$/m', $robots);
        self::assertMatchesRegularExpression('/^Allow: \/$/m', $robots);
        self::assertMatchesRegularExpression('/^Sitemap: https:\/\/[^\s]+\/sitemap\.xml$/m', $robots);
    }

    public function testSitemapOnlyContainsIndexablePublicPages(): void
    {
        $sitemap = file_get_contents(self::projectFile('public/sitemap.xml'));

        self::assertIsString($sitemap, 'public/sitemap.xml must exist so the web server can return it directly.');
        self::assertStringContainsString('<loc>https://sysgc-app-production-5ou4vq.laravel.cloud/</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://sysgc-app-production-5ou4vq.laravel.cloud/a-propos</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://sysgc-app-production-5ou4vq.laravel.cloud/contact</loc>', $sitemap);
        self::assertStringNotContainsString('/connexion', $sitemap);
        self::assertStringNotContainsString('/inscription', $sitemap);
        self::assertStringNotContainsString('/login/check-email', $sitemap);
        self::assertStringNotContainsString('/dashboard', $sitemap);
    }

    public function testWebAppManifestIsPublishedAtTheLinkedLocation(): void
    {
        $manifest = file_get_contents(self::projectFile('public/manifest.json'));

        self::assertIsString($manifest, 'public/manifest.json must exist so the linked resource returns 200.');
        $decoded = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('SysGC', $decoded['name'] ?? null);
    }

    private static function projectFile(string $relativePath): string
    {
        $projectDirectory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);

        return $projectDirectory.'/'.$relativePath;
    }
}
