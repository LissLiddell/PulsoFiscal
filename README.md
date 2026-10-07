# Pulso Fiscal — Invoice Lifecycle Demo

Pulso Fiscal is a portfolio project built with **Laravel 13, Livewire 4, and PostgreSQL**. Its interface and business rules run in a single PHP application. The UI is currently in Spanish.

The demo uses entirely fictional invoices and payments. It calculates monetary amounts in integer cents and provides an interactive check of sample data. That check is only a simulation: **the application does not issue, stamp, validate, or cancel CFDI documents, and it does not connect to Mexico's SAT or a certified provider (PAC)**. Do not enter real taxpayer IDs, XML files, customer data, or credentials.

## Features

- A dashboard showing active invoiced amounts, collected amounts, open balances, and overdue balances as of the selected date.
- A full-width invoice catalog with search and status filters. Selecting an invoice opens a modal with payment details on the left and a document trail on the right; the panels stack on mobile.
- CSV import for fictional invoices, launched from a catalog button. The modal accepts a file or pasted CSV, reports row errors and duplicates, and previews the effect on dashboard totals. Accepted invoices exist only in the visitor's session, can be removed, and support the same simulated payments. The filtered catalog can also be exported as CSV.
- Simulated payments with an amount and date. They update totals, balances, and statuses within each visitor's session and can be reset without changing PostgreSQL.
- Status-specific views: invoices with a balance, including overdue ones, allow test payments; paid invoices show collection closure and a possible next documentation step without issuing a document; canceled invoices remain available for reference.
- A fictional document record separate from payment status, with sample PUE/PPD methods, data status, and a payment timeline. PPD examples show a pending follow-up for each payment; the PUE example paid at registration does not show a payment complement. No tax documents are produced.
- A technical sample-data check for active invoices with a balance. The concept can be corrected on screen without saving the change or writing a CFDI.
- Idempotent sample data and automated tests for calculations, cutoff dates, simulation, and seeding.

Visitor interactions do not write to the database: imported invoices and simulated payments live temporarily in the session. The demo does not move money or issue tax documents.

## Try the CSV import

In the catalog, click **Importar facturas ficticias** to open the import modal. Download the sample file or paste UTF-8 CSV whose first line is exactly:

```csv
referencia,cliente,concepto,emision,vencimiento,importe,estado
```

The columns must appear in that order and be separated by commas. Each following line is one invoice, for example:

```csv
EJ-901,Cliente de prueba,Servicio de muestra,2026-10-01,2026-10-07,1250.00,vigente
```

The issue date cannot be in the future, the due date must be on or after the issue date, and the amount must be positive. Use a period as the decimal separator, without `$` or thousands separators. The accepted status values are `vigente` (active) and `cancelada` (canceled). Each file can contain up to 50 rows and be at most 100 KB; a session can hold up to 100 imported invoices. References must be unique across the CSV, the original demo invoices, and previous imports. Imports create fictional PPD invoices without initial payments; they neither upload nor generate CFDI documents.

Review the preview and confirm the valid rows. **Quitar importación de prueba** removes imported invoices and their simulated payments, but not the preloaded data. **Exportar vista CSV** downloads the rows matching the current search and filters. The exported report is different from the import template: it includes collected and balance columns, and formats amounts with `$` and thousands separators for readability. It cannot be re-imported unchanged.

## Run locally

Requirements: PHP 8.3 or newer with `mbstring` and `pdo_pgsql`, Composer, and PostgreSQL. The isolated tests also require `pdo_sqlite` and `sqlite3`.

1. Run `composer install` in this directory.
2. Copy `.env.example` to `.env`, configure your local PostgreSQL connection, and run `php artisan key:generate`.
3. Run `php artisan migrate --seed`, then `php artisan serve`. If you have run an earlier version of the demo, run `php artisan migrate --seed` again to add the sample document records.
4. Open `http://localhost:8000`.
5. Run `php artisan test` to verify the rules. On Windows, if the SQLite extensions are installed but not enabled in `php.ini`, you can run `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit`.

Never commit a real database connection string, application key, or other credentials, and keep them out of screenshots.

## Scope

Real CFDI integration is outside this demo and would require separate technical and legal validation. The current importer detects and skips duplicates; a larger product could add explicit record reconciliation, authentication, authorization, privacy controls, and auditing.
