<?php

declare(strict_types=1);

namespace App\Safety\Infrastructure;

use App\Project\Domain\Model\Project;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class PhotoUploader
{
    private const int MAX_BYTES = 5_242_880;
    private const int MAX_WIDTH = 1200;

    public function __construct(private string $storageDirectory)
    {
    }

    public function upload(UploadedFile $file, Project $project, \DateTimeInterface $date): string
    {
        $this->assertValidImage($file);
        $source = $file->getPathname();
        $image = $this->createImage($source, $file->getMimeType());
        if (false === $image) {
            throw new \InvalidArgumentException('Le fichier image est invalide.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $target = $image;

        if ($width > self::MAX_WIDTH) {
            $targetHeight = max(1, (int) round($height * (self::MAX_WIDTH / $width)));
            $target = imagecreatetruecolor(self::MAX_WIDTH, $targetHeight);
            imagecopyresampled($target, $image, 0, 0, 0, 0, self::MAX_WIDTH, $targetHeight, $width, $height);
        }

        $projectId = (string) $project->getId();
        $day = \DateTimeImmutable::createFromInterface($date)->format('Y-m-d');
        $relativeDirectory = sprintf('safety_photos/%s/%s', $projectId, $day);
        $directory = $this->storageDirectory.'/'.$relativeDirectory;

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire des photos de sécurité.');
        }

        $filename = bin2hex(random_bytes(16)).'.jpg';
        $destination = $directory.'/'.$filename;
        if (! imagejpeg($target, $destination, 80)) {
            throw new \RuntimeException('Impossible d’enregistrer la photo de sécurité.');
        }

        if ($target !== $image) {
            imagedestroy($target);
        }
        imagedestroy($image);

        return $relativeDirectory.'/'.$filename;
    }

    private function assertValidImage(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Une photo ne peut pas dépasser 5 Mo.');
        }

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG ou WEBP sont acceptées.');
        }
    }

    private function createImage(string $source, ?string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            'image/webp' => imagecreatefromwebp($source),
            default => false,
        };
    }
}
