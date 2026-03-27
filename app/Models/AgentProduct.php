<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProduct extends Model
{
    protected $fillable = [
        'agent_id',
        'product_id',
        'label',
        'icon',
        'currency',
        'min_amount',
        'quick_amounts',
    ];

    protected $casts = [
        'quick_amounts' => 'array',
        'min_amount' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
