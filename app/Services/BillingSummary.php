<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BillingSummary
{
    /**
     * Los cuatro resultados son centavos enteros. La colección debe contener
     * únicamente facturas de la misma organización y sus pagos precargados.
     *
     * @param Collection<int, Invoice> $invoices
     * @param array<int, list<array{reference: string, paid_on: string, amount_cents: int}>> $demoPayments
     * @return array{issued: int, collected: int, open: int, overdue: int}
     */
    public function snapshot(Collection $invoices, CarbonInterface $cutoff, array $demoPayments = []): array
    {
        $result = ['issued' => 0, 'collected' => 0, 'open' => 0, 'overdue' => 0];

        foreach ($invoices as $invoice) {
            if ($invoice->status !== 'vigente' || $invoice->issued_on->gt($cutoff)) {
                continue;
            }

            $paid = $this->paidThrough($invoice, $cutoff, $demoPayments);
            $open = max(0, $invoice->amount_cents - $paid);

            $result['issued'] += $invoice->amount_cents;
            $result['collected'] += $paid;
            $result['open'] += $open;

            if ($invoice->due_on->lt($cutoff)) {
                $result['overdue'] += $open;
            }
        }

        return $result;
    }

    /**
     * @param array<int, list<array{reference: string, paid_on: string, amount_cents: int}>> $demoPayments
     */
    public function paidThrough(Invoice $invoice, CarbonInterface $cutoff, array $demoPayments = []): int
    {
        $recorded = (int) $invoice->payments
            ->filter(fn ($payment) => $payment->paid_on->lte($cutoff))
            ->sum('amount_cents');

        $simulated = (int) collect($demoPayments[$invoice->id] ?? [])
            ->filter(fn (array $payment) => $payment['paid_on'] <= $cutoff->toDateString())
            ->sum('amount_cents');

        return $recorded + $simulated;
    }
}
