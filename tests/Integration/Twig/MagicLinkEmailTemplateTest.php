<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Proves the magic-link email template renders without error and carries the
 * login link plus the 15-minute expiry notice.
 *
 * @see specs/04-auth.md (magic link)
 */
final class MagicLinkEmailTemplateTest extends KernelTestCase
{
    public function testMagicLinkEmailRendersWithLinkAndExpiryNotice(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $html = $twig->render('emails/magic_link.html.twig', [
            'url' => 'https://example.com/connexion/verifier?token=abc&email=foo@bar.com',
        ]);

        self::assertStringContainsString('https://example.com/connexion/verifier', $html);
        self::assertStringContainsString('Me connecter', $html);
        self::assertStringContainsString('Ce lien expire dans 15 minutes.', $html);
        self::assertStringContainsString('Connexion à votre compte', $html);
    }
}
