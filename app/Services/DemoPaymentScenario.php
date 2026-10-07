<?php

namespace App\Services;

use App\Models\Invoice;

class DemoPaymentScenario
{
    private const SESSION_KEY = 'pulso_fiscal_demo_payments';

    /**
     * @return array<int, list<array{reference: string, paid_on: string, amount_cents: int}>>
     */
    public function all(): array
    {
        $payments = session()->get(self::SESSION_KEY, []);

        return is_array($payments) ? $payments : [];
    }

    /**
     * @return array{reference: string, paid_on: string, amount_cents: int}
     */
    public function add(Invoice $invoice, int $amountCents, string $paidOn): array
    {
        $all = $this->all();
        $entries = $all[$invoice->id] ?? [];
        $payment = [
            'reference' => sprintf('SIM-%s-%02d', $invoice->reference, count($entries) + 1),
            'paid_on' => $paidOn,
            'amount_cents' => $amountCents,
        ];

        $entries[] = $payment;
        $all[$invoice->id] = $entries;
        session()->put(self::SESSION_KEY, $all);

        return $payment;
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->all()));
    }

    public function reset(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** @param list<int> $invoiceIds */
    public function forgetInvoices(array $invoiceIds): void
    {
        $all = $this->all();
        foreach ($invoiceIds as $id) {
            unset($all[$id]);
        }
        session()->put(self::SESSION_KEY, $all);
    }
}
