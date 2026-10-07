<?php

namespace Tests\Feature;

use App\Livewire\BillingDashboard;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\BillingSummary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BillingDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_seeded_fictional_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pulso Fiscal')
            ->assertSee('DEM-102')
            ->assertSee('site-menu-trigger')
            ->assertSee('href="#facturas"', false)
            ->assertSee('class="panel invoice-panel"', false)
            ->assertSee('Importar facturas ficticias')
            ->assertDontSee('class="import-modal"', false)
            ->assertDontSee('O pegar el contenido CSV')
            ->assertDontSee('class="invoice-modal"', false)
            ->assertSee('Datos ficticios');
    }

    public function test_invoice_detail_opens_in_two_column_modal_and_can_close(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-103')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('class="invoice-modal"', false)
            ->assertSee('class="invoice-modal-detail"', false)
            ->assertSee('class="invoice-modal-trace"', false)
            ->assertSee('Cerrar detalle de factura')
            ->assertSee('PAG-103-A')
            ->call('closeInvoice')
            ->assertSet('selectedInvoiceId', null)
            ->assertDontSee('class="invoice-modal"', false)
            ->assertSee('DEM-103');
    }

    public function test_summary_separates_invoiced_collected_open_and_overdue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::where('slug', 'demo')->firstOrFail();
        $invoices = Invoice::with('payments')
            ->where('organization_id', $organization->id)->get();

        $totals = app(BillingSummary::class)->snapshot($invoices, Carbon::today());

        $this->assertSame(43610000, $totals['issued']);
        $this->assertSame(10160000, $totals['collected']);
        $this->assertSame(33450000, $totals['open']);
        $this->assertSame(17950000, $totals['overdue']);
    }

    public function test_historical_cutoff_ignores_future_invoices_and_payments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoices = Invoice::with('payments')->get();

        $totals = app(BillingSummary::class)->snapshot(
            $invoices,
            Carbon::today()->subDays(20),
        );

        $this->assertSame(28110000, $totals['issued']);
        $this->assertSame(4460000, $totals['collected']);
        $this->assertSame(23650000, $totals['open']);
        $this->assertSame(0, $totals['overdue']);
    }

    public function test_simulation_rejects_an_incomplete_record_without_writing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-106')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->call('simulate')
            ->assertSee('Falta una descripción del concepto.')
            ->assertSee('No es una revisión fiscal');

        $this->assertDatabaseCount('invoices', 7);
    }

    public function test_simulation_approves_a_complete_record_but_never_issues_a_cfdi(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-102')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->call('simulate')
            ->assertSee('Datos completos')
            ->assertSee('No se generó ni envió un CFDI.');
    }

    public function test_a_visitor_can_correct_the_draft_without_changing_the_invoice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-106')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->set('draftConcept', 'Servicio de demostración')
            ->call('simulate')
            ->assertSee('Datos completos');

        $this->assertNull($invoice->fresh()->concept);
    }

    public function test_cancelled_record_is_read_only_and_cannot_run_data_check(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-104')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Factura de muestra cancelada')
            ->assertDontSee('Registrar abono de prueba')
            ->assertDontSee('Comprobar datos de muestra')
            ->call('simulate')
            ->assertSet('simulation', null);
    }

    public function test_paid_record_shows_collection_closeout_instead_of_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-103')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Esta factura ya quedó pagada')
            ->assertSee('Seguimiento documental · demo')
            ->assertDontSee('Registrar abono de prueba')
            ->assertDontSee('Comprobar datos de muestra')
            ->call('simulate')
            ->assertSet('simulation', null);
    }

    public function test_ppd_payment_has_a_fictional_document_followup_without_issuing_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-103')->firstOrFail();

        $this->assertSame('PPD', $invoice->payment_method);
        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('MÉTODO DE PAGO · EJEMPLO')
            ->assertSee('PPD')
            ->assertSee('ESTADO DOCUMENTAL · DEMO')
            ->assertSee('Datos de muestra listos para revisión')
            ->assertSee('Seguimiento documental por preparar')
            ->assertSee('PAG-103-A')
            ->assertSee('No se generó ninguno.');
    }

    public function test_pue_paid_at_registration_does_not_show_a_payment_complement_followup(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::with('payments')->where('reference', 'DEM-107')->firstOrFail();

        $this->assertSame('PUE', $invoice->payment_method);
        $this->assertSame($invoice->issued_on->toDateString(), $invoice->payments->first()->paid_on->toDateString());

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Ruta PUE de muestra')
            ->assertDontSee('Seguimiento documental por preparar');
    }

    public function test_unpaid_ppd_and_cancelled_records_have_distinct_document_traces(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unpaid = Invoice::where('reference', 'DEM-105')->firstOrFail();
        $cancelled = Invoice::where('reference', 'DEM-104')->firstOrFail();

        $component = Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $unpaid->id)
            ->assertSee('Aún sin cobros')
            ->assertDontSee('Seguimiento documental por preparar');

        $component->call('selectInvoice', $cancelled->id)
            ->assertSee('Registro de muestra cancelado')
            ->assertSee('Seguimiento detenido')
            ->assertDontSee('Seguimiento documental por preparar');
    }

    public function test_correcting_a_draft_does_not_change_persisted_document_stage(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-106')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Datos de muestra por completar')
            ->set('draftConcept', 'Concepto de prueba')
            ->call('simulate')
            ->assertSee('Datos completos')
            ->assertSee('Datos de muestra por completar');

        $this->assertSame('por_completar', $invoice->fresh()->document_stage);
    }

    public function test_demo_payment_updates_the_dashboard_and_reset_restores_it_without_database_writes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-102')->firstOrFail();

        $component = Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Registrar abono de prueba')
            ->set('paymentAmount', '59500.00')
            ->set('paymentDate', Carbon::today()->toDateString())
            ->call('registerDemoPayment')
            ->assertHasNoErrors()
            ->assertSee('SIM-DEM-102-01')
            ->assertSee('$161,100.00')
            ->assertSee('Esta factura ya quedó pagada')
            ->assertSee('SIM-DEM-102-01')
            ->assertSee('Seguimiento documental por preparar')
            ->assertDontSee('Registrar abono de prueba');

        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseMissing('payments', ['reference' => 'SIM-DEM-102-01']);

        $component->call('resetDemoPayments')
            ->assertDontSee('SIM-DEM-102-01')
            ->assertSee('$101,600.00')
            ->assertSee('$59,500.00')
            ->assertSee('Registrar abono de prueba');

        $this->assertDatabaseCount('payments', 3);
    }

    public function test_demo_payment_rejects_overpayment_and_invalid_dates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-102')->firstOrFail();

        $component = Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAmount', '20000.00')
            ->set('paymentDate', Carbon::today()->toDateString())
            ->call('registerDemoPayment')
            ->assertSee('$39,500.00');

        $component->set('paymentAmount', '40000.00')
            ->call('registerDemoPayment')
            ->assertHasErrors(['paymentAmount'])
            ->assertSee('no superar el saldo pendiente');

        $component->set('paymentAmount', '100.00')
            ->set('paymentDate', Carbon::today()->addDay()->toDateString())
            ->call('registerDemoPayment')
            ->assertHasErrors(['paymentDate']);

        $this->assertDatabaseCount('payments', 3);
    }

    public function test_demo_payment_marks_an_unexpired_invoice_as_partial(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-105')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAmount', '10000.00')
            ->set('paymentDate', Carbon::today()->toDateString())
            ->call('registerDemoPayment')
            ->assertHasNoErrors()
            ->assertSee('SIM-DEM-105-01')
            ->assertSee('$47,500.00')
            ->assertSee('Parcial');

        $this->assertDatabaseCount('payments', 3);
    }

    public function test_cancelled_invoice_rejects_demo_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invoice = Invoice::where('reference', 'DEM-104')->firstOrFail();

        Livewire::test(BillingDashboard::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAmount', '100.00')
            ->call('registerDemoPayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertDatabaseCount('payments', 3);
    }

    public function test_reseeding_does_not_duplicate_demo_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('invoices', 7);
        $this->assertDatabaseCount('payments', 3);
    }

    public function test_csv_preview_flags_duplicates_and_errors_before_adding_only_valid_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $today = Carbon::today()->toDateString();
        $csv = "referencia,cliente,concepto,emision,vencimiento,importe,estado\n"
            ."DEM-102,Cliente repetido,Servicio ficticio,$today,$today,100.00,vigente\n"
            ."IMP-001,Cliente temporal,Servicio ficticio,$today,$today,125.50,vigente\n"
            ."IMP-001,Cliente temporal,Servicio ficticio,$today,$today,25.00,vigente\n"
            ."IMP-002,Cliente temporal,Servicio ficticio,$today,$today,0.00,vigente\n";

        $component = Livewire::test(BillingDashboard::class)
            ->call('openImport')
            ->set('csvText', $csv)
            ->call('previewCsv')
            ->assertSee('1 válidas · 3 por corregir')
            ->assertSee('Referencia duplicada')
            ->assertSee('Importe: mayor que cero')
            ->call('applyCsv')
            ->assertSee('1 factura de muestra agregada')
            ->assertSee('IMP-001')
            ->assertSee('$436,225.50');

        $this->assertDatabaseCount('invoices', 7);
        $this->assertDatabaseMissing('invoices', ['reference' => 'IMP-001']);

        $component->call('resetImportedInvoices')
            ->assertDontSee('IMP-001')
            ->assertSee('$436,100.00');
    }

    public function test_imported_invoice_can_be_opened_and_paid_only_in_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $today = Carbon::today()->toDateString();
        $csv = "referencia,cliente,concepto,emision,vencimiento,importe,estado\n"
            ."IMP-003,Cliente de muestra,Servicio ficticio,$today,$today,250.00,vigente\n";

        $component = Livewire::test(BillingDashboard::class)
            ->call('openImport')
            ->set('csvText', $csv)
            ->call('previewCsv')
            ->call('applyCsv')
            ->assertSee('IMP-003');

        $component->call('selectInvoice', -1)
            ->assertSee('Cliente de muestra')
            ->assertSee('TRAZABILIDAD DOCUMENTAL')
            ->assertSee('Aún sin cobros')
            ->set('paymentAmount', '250.00')
            ->set('paymentDate', $today)
            ->call('registerDemoPayment')
            ->assertHasNoErrors()
            ->assertSee('Esta factura ya quedó pagada')
            ->assertSee('SIM-IMP-003-01');

        $this->assertDatabaseCount('invoices', 7);
        $this->assertDatabaseCount('payments', 3);
        $component->call('resetImportedInvoices')->assertDontSee('SIM-IMP-003-01');
    }

    public function test_csv_rejects_bad_headers_too_many_rows_and_cannot_apply_without_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $today = Carbon::today()->toDateString();
        $header = "referencia,cliente,concepto,emision,vencimiento,importe,estado\n";
        $rows = implode('', array_map(
            fn (int $index): string => "IMP-$index,Cliente temporal,Servicio ficticio,$today,$today,1.00,vigente\n",
            range(100, 150),
        ));

        Livewire::test(BillingDashboard::class)
            ->call('openImport')
            ->call('applyCsv')
            ->assertSee('Primero revisa un CSV')
            ->set('csvText', "folio,cliente\nA,B")
            ->call('previewCsv')
            ->assertSee('Usa estas columnas')
            ->set('csvText', $header.$rows)
            ->call('previewCsv')
            ->assertSee('límite por archivo es de 50 facturas')
            ->call('applyCsv')
            ->assertDontSee('IMP-100');

        $this->assertDatabaseCount('invoices', 7);
    }

    public function test_import_modal_opens_and_closing_discards_only_the_pending_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $today = Carbon::today()->toDateString();
        $csv = "referencia,cliente,concepto,emision,vencimiento,importe,estado\n"
            ."IMP-010,Cliente temporal,Servicio ficticio,$today,$today,100.00,vigente\n";

        Livewire::test(BillingDashboard::class)
            ->assertSee('Importar facturas ficticias')
            ->assertDontSee('class="import-modal"', false)
            ->call('openImport')
            ->assertSet('showImportModal', true)
            ->assertSee('class="import-modal"', false)
            ->assertSee('O pegar el contenido CSV')
            ->set('csvText', $csv)
            ->call('previewCsv')
            ->assertSee('IMP-010')
            ->call('closeImport')
            ->assertSet('showImportModal', false)
            ->assertSet('importPreview', null)
            ->assertDontSee('class="import-modal"', false)
            ->call('openImport')
            ->call('applyCsv')
            ->assertSee('Primero revisa un CSV');

        $this->assertDatabaseCount('invoices', 7);
    }

    public function test_filtered_catalog_can_be_downloaded_as_csv(): void
    {
        $this->seed(DatabaseSeeder::class);

        $component = Livewire::test(BillingDashboard::class)
            ->set('search', 'DEM-103')
            ->set('filter', 'pagada')
            ->call('exportCsv')
            ->assertFileDownloaded('pulso-fiscal-facturas.csv');

        $csv = base64_decode($component->effects['download']['content']);
        $lines = preg_split('/\r?\n/', trim($csv));
        $this->assertCount(2, $lines);
        $fields = str_getcsv($lines[1], ',', '"', '');
        $this->assertSame(['$32,000.00', '$32,000.00', '$0.00'], array_slice($fields, 4, 3));
    }

    public function test_import_instructions_example_is_accepted_in_the_stated_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $today = Carbon::today()->toDateString();
        $csv = "referencia,cliente,concepto,emision,vencimiento,importe,estado\n"
            ."EJ-901,Cliente de prueba,Servicio de muestra,$today,$today,1250.00,vigente\n";

        Livewire::test(BillingDashboard::class)
            ->call('openImport')
            ->assertSee('Para cargar, pega primero esta cabecera exacta')
            ->assertSee('EJ-901,Cliente de prueba,Servicio de muestra,'.$today.','.$today.',1250.00,vigente')
            ->set('csvText', $csv)
            ->call('previewCsv')
            ->assertSee('1 válidas · 0 por corregir')
            ->call('applyCsv')
            ->assertSee('EJ-901');
    }
}
