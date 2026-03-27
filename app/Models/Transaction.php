<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'agent_id',
        'product_id',
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
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
