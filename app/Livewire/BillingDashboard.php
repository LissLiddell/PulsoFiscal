<?php

namespace App\Livewire;

use App\Contracts\Certifier;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\BillingSummary;
use App\Services\DemoInvoiceCsv;
use App\Services\DemoInvoiceScenario;
use App\Services\DemoPaymentScenario;
use App\Services\DocumentTrace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingDashboard extends Component
{
    public string $search = '';
    public string $filter = 'todas';
    public ?int $selectedInvoiceId = null;
    public string $draftConcept = '';
    public string $paymentAmount = '';
    public string $paymentDate = '';
    public string $paymentMessage = '';
    public string $csvText = '';
    public string $importMessage = '';
    public bool $showImportModal = false;

    /** @var array<string, mixed>|null */
    public ?array $importPreview = null;

    /** @var array{approved: bool, message: string, issues: list<string>}|null */
    public ?array $simulation = null;

    public function selectInvoice(int $invoiceId): void
    {
        if ($this->showImportModal) {
            $this->closeImport();
        }

        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $invoice = $this->findInvoice($invoiceId, $organization->id);

        $this->selectedInvoiceId = $invoiceId;
        $this->draftConcept = (string) $invoice->concept;
        $this->simulation = null;
        $this->paymentAmount = '';
        $this->paymentDate = Carbon::today()->toDateString();
        $this->paymentMessage = '';
        $this->resetValidation();
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
        $this->simulation = null;
        $this->paymentAmount = '';
        $this->paymentMessage = '';
        $this->resetValidation();
    }

    public function registerDemoPayment(): void
    {
        if ($this->selectedInvoiceId === null) {
            return;
        }

        $this->resetValidation();
        $this->paymentMessage = '';
        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $invoice = $this->findInvoice($this->selectedInvoiceId, $organization->id);
        $scenario = app(DemoPaymentScenario::class);

        if ($invoice->status !== 'vigente') {
            $this->addError('paymentAmount', 'Una factura cancelada no admite abonos.');
            return;
        }

        $rawAmount = trim($this->paymentAmount);
        if (! preg_match('/^\d{1,9}(?:[.,]\d{1,2})?$/D', $rawAmount)) {
            $this->addError('paymentAmount', 'Escribe un importe válido, con hasta dos decimales.');
            return;
        }

        $parts = explode('.', str_replace(',', '.', $rawAmount));
        $amountCents = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        $open = max(0, $invoice->amount_cents - app(BillingSummary::class)->paidThrough(
            $invoice,
            Carbon::today(),
            $scenario->all(),
        ));

        if ($amountCents < 1 || $amountCents > $open) {
            $this->addError('paymentAmount', 'El abono debe ser mayor que cero y no superar el saldo pendiente.');
            return;
        }

        $paidOn = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->paymentDate);
        if ($paidOn === false || $paidOn->format('Y-m-d') !== $this->paymentDate) {
            $this->addError('paymentDate', 'Elige una fecha válida.');
            return;
        }

        if ($this->paymentDate < $invoice->issued_on->toDateString()
            || $this->paymentDate > Carbon::today()->toDateString()) {
            $this->addError('paymentDate', 'La fecha debe estar entre la emisión y hoy.');
            return;
        }

        $scenario->add($invoice, $amountCents, $this->paymentDate);
        $this->paymentAmount = '';
        $this->simulation = null;
        $this->paymentMessage = 'Abono de prueba aplicado. El saldo y los indicadores ya se actualizaron.';
    }

    public function resetDemoPayments(): void
    {
        app(DemoPaymentScenario::class)->reset();
        $this->paymentAmount = '';
        $this->simulation = null;
        $this->paymentMessage = 'Escenario reiniciado: se quitaron solo los abonos de prueba.';
        $this->resetValidation();
    }

    public function simulate(): void
    {
        if ($this->selectedInvoiceId === null) {
            return;
        }

        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $invoice = $this->findInvoice($this->selectedInvoiceId, $organization->id);

        $paid = app(BillingSummary::class)->paidThrough(
            $invoice,
            Carbon::today(),
            app(DemoPaymentScenario::class)->all(),
        );
        if ($invoice->status !== 'vigente' || $paid >= $invoice->amount_cents) {
            $this->simulation = null;
            return;
        }

        // El borrador cambia solo este objeto en memoria; la demo pública no escribe.
        $invoice->concept = mb_substr(trim($this->draftConcept), 0, 160);
        $this->simulation = app(Certifier::class)->simulate($invoice);
    }

    public function openImport(): void
    {
        $this->selectedInvoiceId = null;
        $this->showImportModal = true;
        $this->importMessage = '';
    }

    public function closeImport(): void
    {
        $this->showImportModal = false;
        $this->csvText = '';
        $this->importPreview = null;
        $this->importMessage = '';
        session()->forget('pulso_fiscal_import_preview');
    }

    public function previewCsv(): void
    {
        $this->importMessage = '';
        session()->forget('pulso_fiscal_import_preview');

        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $references = Invoice::where('organization_id', $organization->id)->pluck('reference')->all();
        $references = array_merge($references, array_column(app(DemoInvoiceScenario::class)->all(), 'reference'));
        $preview = app(DemoInvoiceCsv::class)->inspect($this->csvText, $references, Carbon::today());
        $this->csvText = '';
        $this->importPreview = $preview;

        if ($preview['error'] === null && $preview['valid'] !== []) {
            session()->put('pulso_fiscal_import_preview', $preview['valid']);
        }
    }

    public function applyCsv(): void
    {
        $pending = session()->get('pulso_fiscal_import_preview');
        if (! is_array($pending) || $pending === []) {
            $this->importMessage = 'Primero revisa un CSV con al menos una fila válida.';
            return;
        }

        if (count(app(DemoInvoiceScenario::class)->all()) + count($pending) > 100) {
            $this->importMessage = 'El máximo es de 100 facturas importadas por sesión. Quita la importación de prueba antes de continuar.';
            return;
        }

        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $references = Invoice::where('organization_id', $organization->id)->pluck('reference')->all();
        $references = array_merge($references, array_column(app(DemoInvoiceScenario::class)->all(), 'reference'));
        $existing = array_fill_keys(array_map('mb_strtoupper', $references), true);

        foreach ($pending as $row) {
            if (isset($existing[$row['reference']])) {
                $this->importMessage = 'El catálogo cambió. Vuelve a revisar el CSV para detectar duplicados.';
                session()->forget('pulso_fiscal_import_preview');
                $this->importPreview = null;
                return;
            }
            $existing[$row['reference']] = true;
        }

        $count = app(DemoInvoiceScenario::class)->addMany($pending);
        session()->forget('pulso_fiscal_import_preview');
        $this->importPreview = null;
        $label = $count === 1 ? 'factura de muestra agregada' : 'facturas de muestra agregadas';
        $this->importMessage = "$count $label a esta sesión. No se modificó la base de datos.";
    }

    public function resetImportedInvoices(): void
    {
        app(DemoInvoiceScenario::class)->reset();
        session()->forget('pulso_fiscal_import_preview');
        $this->importPreview = null;
        $this->selectedInvoiceId = null;
        $this->importMessage = 'Se retiraron las facturas importadas y sus abonos ficticios. El catálogo original sigue intacto.';
    }

    private function findInvoice(int $invoiceId, int $organizationId): Invoice
    {
        if ($invoiceId < 0) {
            return app(DemoInvoiceScenario::class)->find($invoiceId, $organizationId)
                ?? abort(404);
        }

        return Invoice::with(['customer', 'payments'])
            ->where('organization_id', $organizationId)
            ->findOrFail($invoiceId);
    }

    public function exportCsv(): StreamedResponse
    {
        $organization = Organization::where('slug', 'demo')->firstOrFail();
        [$invoices, $rows] = $this->catalogRows($organization, Carbon::today(), app(DemoPaymentScenario::class)->all());
        $visible = $this->filteredRows($rows);

        return response()->streamDownload(function () use ($visible): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Referencia', 'Cliente', 'Emisión', 'Vencimiento', 'Importe MXN', 'Cobrado MXN', 'Saldo MXN', 'Estado'], ',', '"', '');

            foreach ($visible as $row) {
                $invoice = $row['invoice'];
                fputcsv($output, [
                    $this->safeCsvText($invoice->reference),
                    $this->safeCsvText($invoice->customer->name),
                    $invoice->issued_on->toDateString(),
                    $invoice->due_on->toDateString(),
                    $this->formatCsvMoney($invoice->amount_cents),
                    $this->formatCsvMoney($row['paid']),
                    $this->formatCsvMoney($row['open']),
                    $row['state'],
                ], ',', '"', '');
            }

            fclose($output);
        }, 'pulso-fiscal-facturas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvText(string $text): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $text) ? "'".$text : $text;
    }

    private function formatCsvMoney(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }

    /** @param array<int, list<array{reference: string, paid_on: string, amount_cents: int}>> $demoPayments
     *  @return array{Collection, Collection}
     */
    private function catalogRows(Organization $organization, Carbon $today, array $demoPayments): array
    {
        $summary = app(BillingSummary::class);
        $invoices = Invoice::with(['customer', 'payments'])
            ->where('organization_id', $organization->id)
            ->orderByDesc('issued_on')
            ->get()
            ->concat(app(DemoInvoiceScenario::class)->invoices($organization->id))
            ->sortByDesc(fn (Invoice $invoice) => $invoice->issued_on->toDateString())
            ->values();

        $rows = $invoices->map(function (Invoice $invoice) use ($summary, $today, $demoPayments): array {
            $paid = $summary->paidThrough($invoice, $today, $demoPayments);
            $open = $invoice->status === 'cancelada' ? 0 : max(0, $invoice->amount_cents - $paid);
            $state = match (true) {
                $invoice->status === 'cancelada' => 'cancelada',
                $open === 0 => 'pagada',
                $invoice->due_on->lt($today) => 'vencida',
                $paid > 0 => 'parcial',
                default => 'pendiente',
            };

            return compact('invoice', 'paid', 'open', 'state');
        });

        return [$invoices, $rows];
    }

    private function filteredRows(Collection $rows): Collection
    {
        $search = Str::lower(trim($this->search));

        return $rows->filter(function (array $row) use ($search): bool {
            $matchesSearch = $search === '' || Str::contains(
                Str::lower($row['invoice']->reference.' '.$row['invoice']->customer->name),
                $search,
            );

            return $matchesSearch && ($this->filter === 'todas' || $row['state'] === $this->filter);
        });
    }

    public function render(): View
    {
        $summary = app(BillingSummary::class);
        $today = Carbon::today();
        $organization = Organization::where('slug', 'demo')->first();
        $scenario = app(DemoPaymentScenario::class);
        $demoPayments = $scenario->all();
        [$invoices, $rows] = $organization
            ? $this->catalogRows($organization, $today, $demoPayments)
            : [collect(), collect()];

        $totals = $summary->snapshot($invoices, $today, $demoPayments);
        $visibleRows = $this->filteredRows($rows);

        $selected = $this->selectedInvoiceId === null
            ? null
            : $rows->first(fn (array $row) => $row['invoice']->id === $this->selectedInvoiceId);

        if ($selected !== null) {
            $recorded = $selected['invoice']->payments->map(fn ($payment): array => [
                'reference' => $payment->reference,
                'paid_on' => $payment->paid_on->format('d/m/Y'),
                'paid_on_iso' => $payment->paid_on->toDateString(),
                'amount_cents' => $payment->amount_cents,
                'simulated' => false,
            ])->all();
            $simulated = array_map(fn (array $payment): array => [
                'reference' => $payment['reference'],
                'paid_on' => Carbon::parse($payment['paid_on'])->format('d/m/Y'),
                'paid_on_iso' => $payment['paid_on'],
                'amount_cents' => $payment['amount_cents'],
                'simulated' => true,
            ], $demoPayments[$selected['invoice']->id] ?? []);
            $selected['movements'] = array_merge($recorded, $simulated);
            $selected['document'] = app(DocumentTrace::class)->forInvoice(
                $selected['invoice'],
                $selected['movements'],
            );
        }

        return view('livewire.billing-dashboard', [
            'organization' => $organization,
            'today' => $today,
            'totals' => $totals,
            'rows' => $visibleRows,
            'selected' => $selected,
            'invoiceCount' => $invoices->count(),
            'demoPaymentCount' => $scenario->count(),
            'importedCount' => count(app(DemoInvoiceScenario::class)->all()),
        ]);
    }
}
