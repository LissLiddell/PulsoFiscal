<?php

namespace App\Services;

use App\Contracts\Certifier;
use App\Models\Invoice;

class DemoCertifier implements Certifier
{
    public function simulate(Invoice $invoice): array
    {
        $issues = [];

        if ($invoice->status !== 'vigente') {
            $issues[] = 'El registro está cancelado y no puede continuar.';
        }

        if (trim((string) $invoice->concept) === '') {
            $issues[] = 'Falta una descripción del concepto.';
        }

        if ($invoice->amount_cents <= 0) {
            $issues[] = 'El importe debe ser mayor que cero.';
        }

        if (! $invoice->customer) {
            $issues[] = 'Falta un cliente de demostración.';
        }

        return [
            'approved' => $issues === [],
            'message' => $issues === []
                ? 'Los datos de muestra pasaron esta comprobación. No se generó ni envió un CFDI.'
                : 'La comprobación de datos encontró campos por corregir.',
            'issues' => $issues,
        ];
    }
}
