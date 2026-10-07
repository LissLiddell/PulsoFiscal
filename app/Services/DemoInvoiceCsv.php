<?php

namespace App\Services;

use Carbon\CarbonInterface;

class DemoInvoiceCsv
{
    private const HEADERS = ['referencia', 'cliente', 'concepto', 'emision', 'vencimiento', 'importe', 'estado'];

    /**
     * @param list<string> $existingReferences
     * @return array{rows: list<array<string, mixed>>, valid: list<array<string, mixed>>, impact: array{issued: int, open: int, overdue: int}, error: ?string}
     */
    public function inspect(string $csv, array $existingReferences, CarbonInterface $today): array
    {
        $result = [
            'rows' => [],
            'valid' => [],
            'impact' => ['issued' => 0, 'open' => 0, 'overdue' => 0],
            'error' => null,
        ];

        if ($csv === '' || strlen($csv) > 100_000 || ! mb_check_encoding($csv, 'UTF-8')) {
            $result['error'] = 'El CSV debe estar en UTF-8 y medir menos de 100 KB.';
            return $result;
        }

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);

        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if (! is_array($headers) || array_map(fn ($value) => mb_strtolower(trim((string) $value)), $headers) !== self::HEADERS) {
                $result['error'] = 'Usa estas columnas, en este orden: '.implode(', ', self::HEADERS).'.';
                return $result;
            }

            $seen = array_fill_keys(array_map('mb_strtoupper', $existingReferences), true);
            $line = 1;

            while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($result['rows']) >= 50) {
                    $result['error'] = 'El límite por archivo es de 50 facturas. Divide el CSV e inténtalo de nuevo.';
                    $result['rows'] = [];
                    $result['valid'] = [];
                    $result['impact'] = ['issued' => 0, 'open' => 0, 'overdue' => 0];
                    return $result;
                }

                $errors = [];
                if (count($cells) !== count(self::HEADERS)) {
                    $errors[] = 'Debe tener exactamente 7 columnas.';
                }
                $cells = array_pad(array_slice($cells, 0, 7), 7, '');
                [$reference, $customer, $concept, $issuedOn, $dueOn, $amount, $status] = array_map(
                    fn ($value) => trim((string) $value),
                    $cells,
                );
                $reference = mb_strtoupper($reference);
                $status = mb_strtolower($status);

                if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/D', $reference)) {
                    $errors[] = 'Referencia: usa 2–30 letras, números, guion o guion bajo.';
                } elseif (isset($seen[$reference])) {
                    $errors[] = 'Referencia duplicada en la demo o en este archivo.';
                }
                if (mb_strlen($customer) < 2 || mb_strlen($customer) > 100) {
                    $errors[] = 'Cliente: entre 2 y 100 caracteres.';
                }
                if (mb_strlen($concept) < 3 || mb_strlen($concept) > 160) {
                    $errors[] = 'Concepto: entre 3 y 160 caracteres.';
                }
                if (! $this->validDate($issuedOn) || $issuedOn > $today->toDateString()) {
                    $errors[] = 'Emisión: fecha válida YYYY-MM-DD, hasta hoy.';
                }
                if (! $this->validDate($dueOn) || ($this->validDate($issuedOn) && $dueOn < $issuedOn)) {
                    $errors[] = 'Vencimiento: fecha válida igual o posterior a la emisión.';
                }
                if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $amount) || (float) $amount <= 0) {
                    $errors[] = 'Importe: mayor que cero, con punto y hasta dos decimales.';
                }
                if (! in_array($status, ['vigente', 'cancelada'], true)) {
                    $errors[] = 'Estado: vigente o cancelada.';
                }

                $parts = explode('.', $amount);
                $amountCents = preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $amount)
                    ? ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0')
                    : 0;

                $row = [
                    'line' => $line,
                    'reference' => $reference,
                    'customer' => $customer,
                    'concept' => $concept,
                    'issued_on' => $issuedOn,
                    'due_on' => $dueOn,
                    'amount_cents' => $amountCents,
                    'status' => $status,
                    'errors' => $errors,
                ];
                $result['rows'][] = $row;

                // Una referencia repetida dentro del CSV invalida sus apariciones posteriores.
                if ($reference !== '') {
                    $seen[$reference] = true;
                }
                if ($errors !== []) {
                    continue;
                }

                $result['valid'][] = $row;
                if ($status === 'vigente') {
                    $result['impact']['issued'] += $amountCents;
                    $result['impact']['open'] += $amountCents;
                    if ($dueOn < $today->toDateString()) {
                        $result['impact']['overdue'] += $amountCents;
                    }
                }
            }

            if ($result['rows'] === []) {
                $result['error'] = 'El CSV no contiene facturas.';
            }

            return $result;
        } finally {
            fclose($stream);
        }
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
