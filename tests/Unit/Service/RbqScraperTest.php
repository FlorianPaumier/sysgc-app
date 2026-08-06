<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RbqScraper;
use App\Service\ValueObject\RbqLicenseResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RbqScraperTest extends TestCase
{
    private const string VALID_LICENSE_HTML = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="license-details">
        <h2>ABC Construction Inc.</h2>
        <p><span class="label">Catégorie:</span> <span class="value">Entrepreneur général</span></p>
        <p><span class="label">Date d'expiration:</span> <span class="value">2025-12-31</span></p>
        <p><span class="label">Statut:</span> <span class="value status-valid">Valid</span></p>
    </div>
</body>
</html>
HTML;

    private const string EXPIRED_LICENSE_HTML = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="license-details">
        <h2>XYZ Roofing</h2>
        <p><span class="label">Catégorie:</span> <span class="value">Entrepreneur spécialisé</span></p>
        <p><span class="label">Date d'expiration:</span> <span class="value">2024-06-30</span></p>
        <p><span class="label">Statut:</span> <span class="value status-expired">Expiré</span></p>
    </div>
</body>
</html>
HTML;

    private const string SUSPENDED_LICENSE_HTML = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="license-details">
        <h2>Quick Fix Pro</h2>
        <p><span class="label">Catégorie:</span> <span class="value">Réparation</span></p>
        <p><span class="label">Date d'expiration:</span> <span class="value">2025-08-15</span></p>
        <p><span class="label">Statut:</span> <span class="value status-suspended">Suspendu</span></p>
    </div>
</body>
</html>
HTML;

    private const string NOT_FOUND_HTML = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="error-message">
        Licence introuvable.
    </div>
</body>
</html>
HTML;

    private const string MALFORMED_HTML = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div>
        <p>Some content</p>
    </div>
</body>
</html>
HTML;

    #[Test]
    public function check_returns_parsed_result_for_valid_license(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $client = new MockHttpClient(new MockResponse(self::VALID_LICENSE_HTML, ['http_code' => 200]));
        $scraper = new RbqScraper($client, $logger);

        $result = $scraper->check('RBQ-1234-5678-90');

        $this->assertTrue($result->found);
        $this->assertSame('ABC Construction Inc.', $result->businessName);
        $this->assertSame('Entrepreneur général', $result->category);
        $this->assertEquals(new \DateTime('2025-12-31'), $result->expiryDate);
        $this->assertSame('valid', $result->status);
        $this->assertNull($result->error);
    }

    #[Test]
    public function check_returns_parsed_result_for_expired_license(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $client = new MockHttpClient(new MockResponse(self::EXPIRED_LICENSE_HTML, ['http_code' => 200]));
        $scraper = new RbqScraper($client, $logger);

        $result = $scraper->check('RBQ-9999-8888-77');

        $this->assertTrue($result->found);
        $this->assertSame('XYZ Roofing', $result->businessName);
        $this->assertSame('Entrepreneur spécialisé', $result->category);
        $this->assertEquals(new \DateTime('2024-06-30'), $result->expiryDate);
        $this->assertSame('expired', $result->status);
        $this->assertNull($result->error);
    }

    #[Test]
    public function check_returns_parsed_result_for_suspended_license(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $client = new MockHttpClient(new MockResponse(self::SUSPENDED_LICENSE_HTML, ['http_code' => 200]));
        $scraper = new RbqScraper($client, $logger);

        $result = $scraper->check('RBQ-5555-4444-33');

        $this->assertTrue($result->found);
        $this->assertSame('Quick Fix Pro', $result->businessName);
        $this->assertSame('Réparation', $result->category);
        $this->assertEquals(new \DateTime('2025-08-15'), $result->expiryDate);
        $this->assertSame('suspended', $result->status);
        $this->assertNull($result->error);
    }

    #[Test]
    public function check_returns_not_found_when_license_missing(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $client = new MockHttpClient(new MockResponse(self::NOT_FOUND_HTML, ['http_code' => 200]));
        $scraper = new RbqScraper($client, $logger);

        $result = $scraper->check('RBQ-0000-0000-00');

        $this->assertFalse($result->found);
        $this->assertNull($result->businessName);
        $this->assertNull($result->category);
        $this->assertNull($result->expiryDate);
        $this->assertNull($result->status);
        $this->assertNull($result->error);
    }

    #[Test]
    public function check_returns_parse_error_on_malformed_html(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $client = new MockHttpClient(new MockResponse(self::MALFORMED_HTML, ['http_code' => 200]));
        $scraper = new RbqScraper($client, $logger);

        $result = $scraper->check('RBQ-1111-2222-33');

        $this->assertTrue($result->found);
        $this->assertSame('parse_error', $result->error);
        $this->assertNull($result->businessName);
        $this->assertNull($result->category);
        $this->assertNull($result->expiryDate);
        $this->assertNull($result->status);
    }

    #[Test]
    public function check_retries_on_429_status(): void
    {
        $callCount = 0;
        $logger = $this->createStub(LoggerInterface::class);

        $client = new MockHttpClient(function () use (&$callCount) {
            ++$callCount;
            if ($callCount < 3) {
                return new MockResponse('', ['http_code' => 429]);
            }
            return new MockResponse(self::VALID_LICENSE_HTML, ['http_code' => 200]);
        });

        $scraper = new RbqScraper($client, $logger);
        $result = $scraper->check('RBQ-1234-5678-90');

        $this->assertSame(3, $callCount);
        $this->assertTrue($result->found);
        $this->assertSame('ABC Construction Inc.', $result->businessName);
    }

    #[Test]
    public function check_retries_on_timeout(): void
    {
        $callCount = 0;
        $logger = $this->createStub(LoggerInterface::class);

        $client = new MockHttpClient(function () use (&$callCount) {
            ++$callCount;
            if ($callCount < 3) {
                throw new \Symfony\Component\HttpClient\Exception\TimeoutException('Connection timed out');
            }
            return new MockResponse(self::VALID_LICENSE_HTML, ['http_code' => 200]);
        });

        $scraper = new RbqScraper($client, $logger);
        $result = $scraper->check('RBQ-1234-5678-90');

        $this->assertSame(3, $callCount);
        $this->assertTrue($result->found);
        $this->assertSame('ABC Construction Inc.', $result->businessName);
    }

    #[Test]
    public function check_returns_timeout_error_after_max_retries(): void
    {
        $logger = $this->createStub(LoggerInterface::class);

        $client = new MockHttpClient(function () {
            throw new \Symfony\Component\HttpClient\Exception\TimeoutException('Connection timed out');
        });

        $scraper = new RbqScraper($client, $logger);
        $result = $scraper->check('RBQ-9999-9999-99');

        $this->assertFalse($result->found);
        $this->assertSame('timeout', $result->error);
    }

    #[Test]
    public function check_returns_rate_limited_after_3_429s(): void
    {
        $logger = $this->createStub(LoggerInterface::class);

        $client = new MockHttpClient(function () {
            return new MockResponse('', ['http_code' => 429]);
        });

        $scraper = new RbqScraper($client, $logger);
        $result = $scraper->check('RBQ-8888-8888-88');

        $this->assertFalse($result->found);
        $this->assertSame('rate_limited', $result->error);
    }
}
