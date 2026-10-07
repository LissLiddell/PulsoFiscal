<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $organization = Organization::updateOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Empresa de muestra'],
        );

        $customers = [];
        foreach (['Estudio Ámbar', 'Taller Horizonte', 'Servicios Nube', 'Proyecto Marea'] as $name) {
            $customers[$name] = Customer::updateOrCreate(
                ['organization_id' => $organization->id, 'name' => $name],
                [],
            );
        }

        $rows = [
            ['DEM-101', 'Estudio Ámbar', 'Consultoría de procesos', -48, -18, 12000000, 'vigente', []],
            ['DEM-102', 'Taller Horizonte', 'Mantenimiento preventivo', -35, -6, 8450000, 'vigente', [['PAG-102-A', -10, 2500000]]],
            ['DEM-103', 'Servicios Nube', 'Implementación de sistema', -21, 9, 3200000, 'vigente', [['PAG-103-A', -14, 3200000]]],
            ['DEM-104', 'Proyecto Marea', 'Asesoría inicial', -18, 12, 1800000, 'cancelada', []],
            ['DEM-105', 'Estudio Ámbar', 'Soporte mensual', -8, 22, 5750000, 'vigente', []],
            ['DEM-106', 'Taller Horizonte', null, -4, 26, 9750000, 'vigente', []],
            ['DEM-107', 'Proyecto Marea', 'Diseño operativo', -55, -25, 4460000, 'vigente', [['PAG-107-A', -55, 4460000]]],
        ];

        foreach ($rows as [$reference, $customer, $concept, $issuedDays, $dueDays, $amount, $status, $payments]) {
            $paymentMethod = $reference === 'DEM-107' ? 'PUE' : 'PPD';
            $documentStage = match ($reference) {
                'DEM-104' => 'cancelado',
                'DEM-106' => 'por_completar',
                default => 'datos_listos',
            };

            $invoice = Invoice::updateOrCreate(
                ['organization_id' => $organization->id, 'reference' => $reference],
                [
                    'customer_id' => $customers[$customer]->id,
                    'concept' => $concept,
                    'issued_on' => $today->copy()->addDays($issuedDays)->toDateString(),
                    'due_on' => $today->copy()->addDays($dueDays)->toDateString(),
                    'amount_cents' => $amount,
                    'status' => $status,
                    'payment_method' => $paymentMethod,
                    'document_stage' => $documentStage,
                ],
            );

            foreach ($payments as [$paymentReference, $paidDays, $paymentAmount]) {
                Payment::updateOrCreate(
                    ['organization_id' => $organization->id, 'reference' => $paymentReference],
                    [
                        'invoice_id' => $invoice->id,
                        'paid_on' => $today->copy()->addDays($paidDays)->toDateString(),
                        'amount_cents' => $paymentAmount,
                    ],
                );
            }
        }
    }
}
