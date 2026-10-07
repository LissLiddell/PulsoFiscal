<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;

class DemoInvoiceScenario
{
    private const SESSION_KEY = 'pulso_fiscal_demo_invoices';

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $rows = session()->get(self::SESSION_KEY, []);

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param list<array<string, mixed>> $rows */
    public function addMany(array $rows): int
    {
        $all = $this->all();
        $nextId = ($all === [] ? 0 : min(0, ...array_column($all, 'id'))) - 1;

        foreach ($rows as $row) {
            $row['id'] = $nextId--;
            unset($row['line'], $row['errors']);
            $all[] = $row;
        }

        session()->put(self::SESSION_KEY, $all);

        return count($rows);
    }

    public function find(int $id, int $organizationId): ?Invoice
    {
        foreach ($this->all() as $row) {
            if ($row['id'] === $id) {
                return $this->toInvoice($row, $organizationId);
            }
        }

        return null;
    }

    /** @return list<Invoice> */
    public function invoices(int $organizationId): array
    {
        return array_map(fn (array $row): Invoice => $this->toInvoice($row, $organizationId), $this->all());
    }

    public function reset(): void
    {
        $ids = array_column($this->all(), 'id');
        app(DemoPaymentScenario::class)->forgetInvoices($ids);
        session()->forget(self::SESSION_KEY);
    }

    private function toInvoice(array $row, int $organizationId): Invoice
    {
        $invoice = new Invoice([
            'organization_id' => $organizationId,
            'reference' => $row['reference'],
            'concept' => $row['concept'],
            'issued_on' => $row['issued_on'],
            'due_on' => $row['due_on'],
            'amount_cents' => $row['amount_cents'],
            'status' => $row['status'],
            'payment_method' => 'PPD',
            'document_stage' => 'datos_listos',
        ]);
        $invoice->id = $row['id'];
        $invoice->setRelation('customer', new Customer(['name' => $row['customer']]));
        $invoice->setRelation('payments', collect());

        return $invoice;
    }
}
