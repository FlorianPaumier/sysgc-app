<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Infrastructure\RbqEvidenceUploader;
use App\Compliance\Domain\Model\Subcontractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RbqEvidenceUploaderTest extends TestCase
{
    private string $storageDirectory;

    protected function setUp(): void
    {
        $this->storageDirectory = sys_get_temp_dir().'/sysgc-rbq-evidence-'.bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->storageDirectory)) {
            return;
        }
        foreach (glob($this->storageDirectory.'/*/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (glob($this->storageDirectory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
        rmdir($this->storageDirectory);
    }

    public function test_pdf_evidence_is_stored_with_a_safe_name_and_original_metadata(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'rbq-pdf-');
        self::assertIsString($source);
        file_put_contents($source, "%PDF-1.1\n%%EOF\n");
        $file = new UploadedFile($source, 'preuve registre.pdf', 'application/pdf', null, true);
        $subcontractor = (new Subcontractor())->setName('Test');

        $metadata = (new RbqEvidenceUploader($this->storageDirectory))->upload($file, $subcontractor);

        self::assertSame('preuve registre.pdf', $metadata['originalName']);
        self::assertMatchesRegularExpression('#^rbq_evidence/[0-9a-f-]+/[0-9a-f]{32}\.pdf$#', $metadata['path']);
        self::assertFileExists($this->storageDirectory.'/'.$metadata['path']);
    }
}
