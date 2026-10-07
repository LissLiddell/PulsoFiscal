<?php

namespace App\Contracts;

use App\Models\Invoice;

interface Certifier
{
    /** @return array{approved: bool, message: string, issues: list<string>} */
    public function simulate(Invoice $invoice): array;
}
