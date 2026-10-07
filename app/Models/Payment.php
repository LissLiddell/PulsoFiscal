<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'organization_id', 'invoice_id', 'reference', 'paid_on', 'amount_cents',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_cents' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
