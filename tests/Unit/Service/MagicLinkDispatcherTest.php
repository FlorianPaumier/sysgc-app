<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Identity\Domain\Model\MagicLink;
use App\Identity\Domain\Model\User;
use App\Identity\Application\MagicLink\SendMagicLinkMessage;
use App\Identity\Application\MagicLink\MagicLinkDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Unit tests for MagicLinkDispatcher.
 * Tests token generation, hashing, persistence, and message dispatch.
 */
final class MagicLinkDispatcherTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $bus;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private MagicLinkDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->dispatcher = new MagicLinkDispatcher(
            $this->entityManager,
            $this->bus,
            $this->urlGenerator,
        );
    }

    public function test_dispatchFor_generates_128_char_hex_token(): void
    {
        $user = $this->createUser('test@example.com');
        $capturedLink = null;
        $capturedToken = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (MagicLink $link) use (&$capturedLink) {
                $capturedLink = $link;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'auth_login_verify',
                $this->callback(function (array $params) use (&$capturedToken) {
                    $capturedToken = $params['token'] ?? null;
                    return isset($params['token'], $params['email'])
                        && is_string($params['token'])
                        && \strlen($params['token']) === 128
                        && $params['email'] === 'test@example.com';
                }),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://sysgc.test/connexion/verifier');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchFor($user);

        $this->assertNotNull($capturedToken);
        $this->assertIsString($capturedToken);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $capturedToken);
        $this->assertNotNull($capturedLink);
    }

    public function test_dispatchFor_stores_sha256_hash_not_raw_token(): void
    {
        $user = $this->createUser('test@example.com');
        $capturedLink = null;
        $rawToken = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (MagicLink $link) use (&$capturedLink) {
                $capturedLink = $link;
                // Extract the raw token that would be in the URL
                $this->assertNotNull($link->getTokenHash());

                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('auth_login_verify', $this->callback(function (array $params) use (&$rawToken) {
            $rawToken = $params['token'] ?? null;
                return is_string($rawToken) && \strlen($rawToken) === 128;
            }), UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://sysgc.test/connexion/verifier');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchFor($user);

        $this->assertNotNull($capturedLink);
        $this->assertNotNull($rawToken);

        $this->assertIsString($rawToken);
        $expectedHash = hash('sha256', $rawToken);
        $this->assertSame($expectedHash, $capturedLink->getTokenHash());
        $this->assertSame(64, \strlen($capturedLink->getTokenHash()));
    }

    public function test_dispatchFor_sets_expiry_15_minutes_in_future(): void
    {
        $user = $this->createUser('test@example.com');
        $capturedLink = null;
        $beforeDispatch = new \DateTimeImmutable();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (MagicLink $link) use (&$capturedLink) {
                $capturedLink = $link;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())->method('generate')->willReturn('https://sysgc.test/verify');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchFor($user);

        $this->assertNotNull($capturedLink);
        $expiry = $capturedLink->getMagicLinkTokenExpiresAt();
        $this->assertNotNull($expiry);

        $afterDispatch = new \DateTimeImmutable();

        // Expiry should be between 14 and 16 minutes from now (allowing for test execution time)
        $minExpected = $beforeDispatch->modify('+14 minutes');
        $maxExpected = $afterDispatch->modify('+16 minutes');

        $this->assertGreaterThan($minExpected, $expiry);
        $this->assertLessThan($maxExpected, $expiry);
    }

    public function test_dispatchFor_sets_owner_on_magicLink(): void
    {
        $user = $this->createUser('test@example.com');
        $capturedLink = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (MagicLink $link) use (&$capturedLink) {
                $capturedLink = $link;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())->method('generate')->willReturn('https://sysgc.test/verify');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchFor($user);

        $this->assertNotNull($capturedLink);
        $this->assertSame($user, $capturedLink->getOwner());
    }

    public function test_dispatchFor_dispatches_message_with_correct_params(): void
    {
        $user = $this->createUser('test@example.com');
        $generatedUrl = 'https://sysgc.test/connexion/verifier?token=abc123&email=test@example.com';

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($generatedUrl);

        $capturedMessage = null;
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendMagicLinkMessage $message) use (&$capturedMessage, $generatedUrl) {
                $capturedMessage = $message;
                return $message->to === 'test@example.com'
                    && $message->subject === 'Connectez-vous à SysGC'
                    && $message->template === 'emails/magic_link.html.twig'
                    && isset($message->context['url'])
                    && $message->context['url'] === $generatedUrl;
            }))
            ->willReturnCallback(fn(SendMagicLinkMessage $message) => new Envelope($message));

        $this->dispatcher->dispatchFor($user);
    }

    public function test_dispatchFor_generates_url_with_absolute_scheme(): void
    {
        $user = $this->createUser('test@example.com');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'auth_login_verify',
                $this->anything(),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://sysgc.test/connexion/verifier');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchFor($user);
    }


    private function createUser(string $email): User
    {
        $user = new User();
        if ($email === '') {
            throw new \InvalidArgumentException('Email must not be empty.');
        }

        $user->setEmail($email);

        return $user;
    }

}
