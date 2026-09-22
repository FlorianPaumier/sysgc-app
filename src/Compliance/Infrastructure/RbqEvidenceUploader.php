<?php

declare(strict_types=1);

namespace App\Compliance\Infrastructure;

use App\Compliance\Domain\Model\Subcontractor;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class RbqEvidenceUploader
{
    private const int MAX_BYTES = 5_242_880;

    public function __construct(private string $storageDirectory)
    {
    }

    /** @return array{originalName: string, path: string} */
    public function upload(UploadedFile $file, Subcontractor $subcontractor): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Une preuve ne peut pas dépasser 5 Mo.');
        }

        $mimeType = $file->getMimeType();
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('La preuve doit être un PDF ou une image JPEG, PNG ou WEBP.'),
        };
        $relativeDirectory = 'rbq_evidence/'.$subcontractor->getId();
        $directory = $this->storageDirectory.'/'.$relativeDirectory;
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire des preuves RBQ.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($directory, $filename);

        return [
            'originalName' => $file->getClientOriginalName(),
            'path' => $relativeDirectory.'/'.$filename,
        ];
    }
}
