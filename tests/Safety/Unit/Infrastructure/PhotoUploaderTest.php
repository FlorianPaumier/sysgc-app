<?php

declare(strict_types=1);

namespace App\Tests\Safety\Unit\Infrastructure;

use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use App\Safety\Infrastructure\PhotoUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PhotoUploaderTest extends TestCase
{
    private string $storageDirectory;

    protected function setUp(): void
    {
        $this->storageDirectory = sys_get_temp_dir().'/sysgc-safety-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storageDirectory)) {
            array_map(unlink(...), glob($this->storageDirectory.'/**/*') ?: []);
            rmdir($this->storageDirectory);
        }
    }

    public function test_rejects_a_file_that_is_not_an_allowed_image_type(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'safety-photo-');
        self::assertNotFalse($source);
        file_put_contents($source, 'not an image');
        $file = new UploadedFile($source, 'hazard.pdf', 'application/pdf', null, true);
        $project = new Project($this->createStub(Organization::class), '123 rue Principale', 'Client');

        $uploader = new PhotoUploader($this->storageDirectory);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JPEG, PNG ou WEBP');
        $uploader->upload($file, $project, new \DateTimeImmutable('2026-06-09'));
    }
}
