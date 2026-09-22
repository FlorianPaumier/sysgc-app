<?php

declare(strict_types=1);

namespace App\Commercial\Application;

final readonly class InvoiceTaxCalculator
{
    /** @return array{subtotal: string, tps: string, tvq: string, total: string} */
    public function calculate(string $subtotal): array
    {
        $base = $this->cents($subtotal);
        $tps = (int) round($base * 0.05, 0, PHP_ROUND_HALF_UP);
        $tvq = (int) round($base * 0.09975, 0, PHP_ROUND_HALF_UP);

        return [
            'subtotal' => $this->money($base),
            'tps' => $this->money($tps),
            'tvq' => $this->money($tvq),
            'total' => $this->money($base + $tps + $tvq),
        ];
    }

    private function cents(string $amount): int
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('Le montant doit être monétaire.');
        }

        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
