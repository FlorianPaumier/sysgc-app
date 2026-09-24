<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalControllerTest extends WebTestCase
{
    #[DataProvider('legalPageProvider')]
    public function testLegalPagesArePublicAndUseThePublicLayout(string $path, string $heading): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-frontend="public"]');
        self::assertSelectorTextSame('main h1', $heading);
        self::assertSelectorNotExists('[data-app-chrome]');
    }

    /** @return iterable<string, array{string, string}> */
    public static function legalPageProvider(): iterable
    {
        yield 'privacy' => ['/confidentialite', 'Politique de confidentialité'];
        yield 'terms' => ['/conditions-utilisation', "Conditions d’utilisation"];
    }

    public function testPrivacyPolicyCoversLaw25ObligationsAndNamedSuppliers(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/confidentialite');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main article[aria-labelledby="legal-title"]');

        $text = $crawler->filter('main')->text();
        foreach ([
            'Renseignements personnels recueillis',
            'Finalités',
            'Moyens de collecte',
            'Fournisseurs et communication',
            'Laravel Cloud',
            'Cloudflare',
            'Stripe',
            'Resend',
            'Google Fonts',
            'hors du Québec',
            'évaluation des facteurs relatifs à la vie privée',
            'Conservation et destruction',
            'Mesures de sécurité',
            'Vos droits',
            'Retrait du consentement',
            'Plainte et incident de confidentialité',
            'Témoins',
            'Responsable de la protection des renseignements personnels',
            'À compléter',
        ] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
    }

    public function testTermsCoverB2bResponsibilitiesAndProductLimits(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/conditions-utilisation');

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('main')->text();
        foreach ([
            'entreprises et professionnels',
            'Offre, prix et renouvellement',
            'Responsabilité réglementaire',
            'Données du client',
            'Résiliation, exportation et suppression',
            'Limitation de responsabilité',
            'À faire valider par un conseiller juridique',
            'n’est ni affilié',
            'CCQ',
            'RBQ',
            'CNESST',
            'À compléter',
        ] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
    }

    public function testFooterLinksToBothLegalPages(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('footer a[href="/confidentialite"]');
        self::assertSelectorExists('footer a[href="/conditions-utilisation"]');
    }

    #[DataProvider('personalInformationFormProvider')]
    public function testPersonalInformationFormsLinkToThePrivacyNotice(string $path, string $formSelector): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists($formSelector.' [data-privacy-notice]');
        self::assertSelectorExists($formSelector.' a[href="/confidentialite"]');
    }

    /** @return iterable<string, array{string, string}> */
    public static function personalInformationFormProvider(): iterable
    {
        yield 'registration' => ['/inscription', 'form[name="registration"]'];
        yield 'contact' => ['/contact', 'form[name="contact"]'];
        yield 'homepage lead form' => ['/', 'form.cta-form'];
    }
}
