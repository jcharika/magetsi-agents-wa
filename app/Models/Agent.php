<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'ecocash_number',
        'wa_id',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(AgentProduct::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getProductOrDefault(string $productId): array
    {
        $product = $this->products()->where('product_id', $productId)->first();

        if ($product) {
            return $product->toArray();
        }

        // Platform defaults
        return [
            'product_id' => 'zesa',
            'label' => 'ZESA Tokens',
            'icon' => '⚡',
            'currency' => 'ZWG',
            'min_amount' => 100,
            'quick_amounts' => [100, 200, 300, 500],
        ];
    }
}
