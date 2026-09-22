<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\LicenseVerifier;
use App\Compliance\Domain\Model\Subcontractor;
use App\Compliance\Infrastructure\RbqScraper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final class LicenseVerifierStateTest extends TestCase
{
    public function test_registry_failure_preserves_the_last_successful_verification(): void
    {
        $lastSuccess = new \DateTimeImmutable('2026-09-01 09:30:00');
        $subcontractor = (new Subcontractor())
            ->setName('Construction Exemple')
            ->setRbqLicenseNumber('RBQ-1234-5678-90')
            ->setVerified(true)
            ->setLastVerifiedAt($lastSuccess)
            ->setCategory('Entrepreneur général')
            ->setExpiryDate(new \DateTimeImmutable('2027-08-31'));

        $verifier = $this->verifierFor(new MockResponse('<html><body>structure inconnue</body></html>', ['http_code' => 200]));

        $result = $verifier->verifySingle($subcontractor);

        self::assertSame('parse_error', $result->error);
        self::assertTrue($subcontractor->isVerified());
        self::assertSame($lastSuccess, $subcontractor->getLastVerifiedAt());
        self::assertSame('Entrepreneur général', $subcontractor->getCategory());
        self::assertEquals(new \DateTimeImmutable('2027-08-31'), $subcontractor->getExpiryDate());
    }

    public function test_registry_failure_marks_the_license_for_manual_review(): void
    {
        $subcontractor = (new Subcontractor())
            ->setName('Construction Exemple')
            ->setRbqLicenseNumber('RBQ-1234-5678-90');

        $this->verifierFor(new MockResponse('<html><body>structure inconnue</body></html>', ['http_code' => 200]))
            ->verifySingle($subcontractor);

        self::assertTrue($subcontractor->requiresManualReview());
        self::assertNotNull($subcontractor->getLastVerificationAttemptAt());
        self::assertSame('parse_error', $subcontractor->getLastVerificationError());
    }

    public function test_successful_registry_result_replaces_the_current_verification_evidence(): void
    {
        $html = <<<'HTML'
            <div class="license-details">
                <h2>Construction Registre inc.</h2>
                <p><span class="label">Catégorie:</span> <span class="value">Entrepreneur général</span></p>
                <p><span class="label">Date d'expiration:</span> <span class="value">2027-12-31</span></p>
                <p><span class="label">Statut:</span> <span class="value status-valid">Valide</span></p>
            </div>
            HTML;
        $subcontractor = (new Subcontractor())
            ->setName('Ancien nom')
            ->setRbqLicenseNumber('RBQ-1234-5678-90');

        $this->verifierFor(new MockResponse($html, ['http_code' => 200]))->verifySingle($subcontractor);

        self::assertTrue($subcontractor->isVerified());
        self::assertFalse($subcontractor->requiresManualReview());
        self::assertNotNull($subcontractor->getLastVerifiedAt());
        self::assertSame('Registre RBQ', $subcontractor->getVerificationSource());
        self::assertSame('Construction Registre inc.', $subcontractor->getVerifiedBusinessName());
        self::assertSame('Entrepreneur général', $subcontractor->getCategory());
        self::assertEquals(new \DateTimeImmutable('2027-12-31'), $subcontractor->getExpiryDate());
    }

    private function verifierFor(MockResponse $response): LicenseVerifier
    {
        return new LicenseVerifier(
            new RbqScraper(new MockHttpClient($response), $this->createStub(LoggerInterface::class)),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MessageBusInterface::class),
        );
    }
}
