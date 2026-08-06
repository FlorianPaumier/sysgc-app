<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\ValueObject\RbqLicenseResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Scrapes RBQ public registry to verify subcontractor licenses.
 */
final readonly class RbqScraper
{
    private const string BASE_URL = 'https://www.rbq.gouv.qc.ca/soaitec/asp/RegistreLicRecherche.asp';
    private const int TIMEOUT_SECONDS = 10;
    private const int MAX_RETRIES = 3;
    private const array RETRY_DELAYS = [2, 5, 10]; // seconds

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $rbqLogger,
    ) {}

    /**
     * Check a license number against RBQ registry.
     *
     * @param string $licenseNumber RBQ license number (e.g., "RBQ-1234-5678-90")
     * @return RbqLicenseResult Verification result
     */
    public function check(string $licenseNumber): RbqLicenseResult
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            ++$attempt;
            $this->rbqLogger->info('RBQ check attempt', [
                'license' => $this->maskLicense($licenseNumber),
                'attempt' => $attempt,
            ]);

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL, [
                    'query' => ['licence' => $licenseNumber],
                    'timeout' => self::TIMEOUT_SECONDS,
                    'headers' => ['User-Agent' => 'SysGC/1.0 (+https://sysgc.com)'],
                ]);

                $statusCode = $response->getStatusCode();

                // Rate limit or server error -> retry
                if ($statusCode === 429 || $statusCode >= 500) {
                    $this->rbqLogger->warning('RBQ retryable HTTP status', [
                        'license' => $this->maskLicense($licenseNumber),
                        'status' => $statusCode,
                        'attempt' => $attempt,
                    ]);

                    if ($attempt < self::MAX_RETRIES) {
                        $this->waitBeforeRetry($attempt);
                        continue;
                    }

                    return new RbqLicenseResult(error: 'rate_limited');
                }

                $html = $response->getContent();
                $result = $this->parseHtml($html);

                $this->rbqLogger->info('RBQ check complete', [
                    'license' => $this->maskLicense($licenseNumber),
                    'found' => $result->found,
                    'status' => $result->status,
                ]);

                return $result;
            } catch (\Symfony\Component\HttpClient\Exception\TimeoutException) {
                $this->rbqLogger->warning('RBQ timeout', [
                    'license' => $this->maskLicense($licenseNumber),
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $this->waitBeforeRetry($attempt);
                    continue;
                }

                return new RbqLicenseResult(error: 'timeout');
            } catch (\Exception $e) {
                $this->rbqLogger->error('RBQ network error', [
                    'license' => $this->maskLicense($licenseNumber),
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $this->waitBeforeRetry($attempt);
                    $lastException = $e;
                    continue;
                }

                return new RbqLicenseResult(error: 'network_error');
            }
        }

        // Should not reach here, but return error if exhausted retries with exception
        return new RbqLicenseResult(error: 'network_error');
    }

    /**
     * Parse RBQ HTML response.
     */
    private function parseHtml(string $html): RbqLicenseResult
    {
        $crawler = new Crawler($html);

        // Check for "not found" message
        $notFound = $crawler->filter('.error-message');
        if ($notFound->count() > 0) {
            return new RbqLicenseResult(found: false);
        }

        // Try to parse license details
        try {
            $businessName = $crawler->filter('.license-details h2')->text();
            $category = $crawler->filter('.license-details .value')->eq(0)->text();
            $expiryText = $crawler->filter('.license-details .value')->eq(1)->text();
            $statusElement = $crawler->filter('.license-details .value')->eq(2);

            $status = null;
            if ($statusElement->count() > 0) {
                $statusText = $statusElement->text();
                $status = match (true) {
                    // 'invalide' must be checked before 'valide'/'valid' — 'invalide' contains both
                    str_contains(strtolower($statusText), 'invalide') => 'invalid',
                    str_contains(strtolower($statusText), 'valide') => 'valid',
                    str_contains(strtolower($statusText), 'valid') => 'valid',
                    str_contains(strtolower($statusText), 'expir') => 'expired',
                    str_contains(strtolower($statusText), 'suspend') => 'suspended',
                    default => null,
                };
            }

            $expiryDate = null;
            if ($expiryText) {
                $date = \DateTime::createFromFormat('Y-m-d', trim($expiryText));
                if ($date) {
                    $date->setTime(0, 0, 0);
                    $expiryDate = $date;
                }
            }

            return new RbqLicenseResult(
                found: true,
                businessName: trim($businessName),
                category: trim($category),
                expiryDate: $expiryDate,
                status: $status,
            );
        } catch (\Exception $e) {
            $this->rbqLogger->error('RBQ parse error', [
                'error' => $e->getMessage(),
                'html_length' => strlen($html),
            ]);

            // Found license but failed to parse -> flag for manual review
            return new RbqLicenseResult(
                found: true,
                error: 'parse_error',
            );
        }
    }

    private function waitBeforeRetry(int $attempt): void
    {
        $delay = self::RETRY_DELAYS[$attempt - 1] ?? self::RETRY_DELAYS[2];
        \sleep($delay);
    }

    private function maskLicense(string $license): string
    {
        if (strlen($license) <= 4) {
            return '***';
        }

        return substr($license, 0, 4).'***';
    }
}
