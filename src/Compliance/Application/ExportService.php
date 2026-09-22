<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Compliance\Domain\Model\CCQDeclaration;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final readonly class ExportService
{
    public function __construct(
        private Environment $twig,
        private string $exportDirectory,
    ) {}

    /** Returns the generated local PDF path. */
    public function generatePdf(CCQDeclaration $declaration): string
    {
        if (!is_dir($this->exportDirectory) && !mkdir($concurrentDirectory = $this->exportDirectory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Impossible de créer le répertoire des exports CCQ.');
        }

        $html = $this->twig->render('ccq/export.html.twig', [
            'declaration' => $declaration,
            'organization' => $declaration->getOrganization(),
            'project' => $declaration->getProject(),
            'footer' => 'Document généré par SysGC — non officiel',
        ]);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $path = rtrim($this->exportDirectory, '/').'/ccq-'.$declaration->getId()->toRfc4122().'.pdf';
        if (file_put_contents($path, $pdf->output()) === false) {
            throw new \RuntimeException('Impossible d’écrire le PDF CCQ.');
        }

        return $path;
    }
}
