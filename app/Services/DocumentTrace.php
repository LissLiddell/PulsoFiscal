<?php

namespace App\Services;

use App\Models\Invoice;

class DocumentTrace
{
    /**
     * @param list<array{reference: string, paid_on: string, paid_on_iso: string, amount_cents: int, simulated: bool}> $movements
     * @return array{method: string, method_detail: string, stage: string, stage_label: string, stage_detail: string, events: list<array{date: string, title: string, detail: string, kind: string}>}
     */
    public function forInvoice(Invoice $invoice, array $movements): array
    {
        $method = $invoice->payment_method === 'PUE' ? 'PUE' : 'PPD';
        $cancelled = $invoice->status === 'cancelada';
        $stage = $cancelled ? 'cancelado' : $invoice->document_stage;

        [$stageLabel, $stageDetail] = match ($stage) {
            'datos_listos' => [
                'Datos de muestra listos para revisión',
                'La ficha tiene los campos básicos de esta demo; no significa que exista un CFDI timbrado.',
            ],
            'por_completar' => [
                'Datos de muestra por completar',
                'Falta información en la ficha. Corregir el borrador en pantalla no modifica este registro.',
            ],
            'cancelado' => [
                'Registro de muestra cancelado',
                'Se conserva la referencia para consulta. No se preparan nuevos documentos desde este registro.',
            ],
            default => [
                'Registro de muestra capturado',
                'La ficha está capturada, pero aún no tiene una revisión documental.',
            ],
        };

        $events = [[
            'date' => $invoice->issued_on->format('d/m/Y'),
            'title' => 'Registro de factura de muestra',
            'detail' => 'Referencia '.$invoice->reference.' · No equivale a un CFDI emitido.',
            'kind' => 'record',
        ]];

        if ($cancelled) {
            $events[] = [
                'date' => 'Sin fecha fiscal',
                'title' => 'Seguimiento detenido',
                'detail' => 'El registro figura como cancelado en esta demo; no se simula una cancelación ante el SAT.',
                'kind' => 'closed',
            ];
        } else {
            usort($movements, fn (array $a, array $b): int => [$a['paid_on_iso'], $a['reference']] <=> [$b['paid_on_iso'], $b['reference']]);

            foreach ($movements as $movement) {
                $events[] = [
                    'date' => $movement['paid_on'],
                    'title' => $movement['simulated'] ? 'Abono ficticio' : 'Pago de muestra',
                    'detail' => $movement['reference'].' · $'.number_format($movement['amount_cents'] / 100, 2).' MXN',
                    'kind' => 'payment',
                ];

                if ($method === 'PPD') {
                    $events[] = [
                        'date' => $movement['paid_on'],
                        'title' => 'Seguimiento documental por preparar',
                        'detail' => 'Ejemplo asociado a '.$movement['reference'].': aquí podría prepararse un complemento de recepción de pagos. No se generó ninguno.',
                        'kind' => 'next',
                    ];
                }
            }

            if ($method === 'PUE') {
                $events[] = [
                    'date' => $invoice->issued_on->format('d/m/Y'),
                    'title' => 'Ruta PUE de muestra',
                    'detail' => 'El pago de este ejemplo coincide con la fecha del registro; no se muestra complemento de pago.',
                    'kind' => 'note',
                ];
            } elseif ($movements === []) {
                $events[] = [
                    'date' => 'Próximo movimiento',
                    'title' => 'Aún sin cobros',
                    'detail' => 'Cuando se registre un pago de muestra, aparecerá aquí su seguimiento documental ficticio.',
                    'kind' => 'note',
                ];
            }
        }

        return [
            'method' => $method,
            'method_detail' => $method === 'PUE'
                ? 'Pago en una sola exhibición, en el ejemplo de esta demo.'
                : 'Pago en parcialidades o diferido, en el ejemplo de esta demo.',
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'stage_detail' => $stageDetail,
            'events' => $events,
        ];
    }
}
