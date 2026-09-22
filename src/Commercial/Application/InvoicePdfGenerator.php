<?php

declare(strict_types=1);

namespace App\Commercial\Application;

use App\Commercial\Domain\Model\Invoice;
use Dompdf\Dompdf;
use Twig\Environment;

final readonly class InvoicePdfGenerator
{
    public function __construct(private Environment $twig)
    {
    }

    public function generate(Invoice $invoice): string
    {
        $pdf = new Dompdf();
        $pdf->loadHtml($this->twig->render('commercial/invoice_pdf.html.twig', ['invoice' => $invoice]));
        $pdf->setPaper('letter');
        $pdf->render();

        return $pdf->output();
    }
}
