<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'agent_id',
        'product_id',
        'handler',
        'meter_number',
        'customer_name',
        'customer_address',
        'amount',
        'currency',
        'ecocash_number',
        'recipient_phone',
        'status',
        'token',
        'reference',
        'trace',
        'uid',
        'external_uid',
        'biller_status',
        'payment_status',
        'payment_amount',
        'customer_reference',
        'api_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'api_response' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
