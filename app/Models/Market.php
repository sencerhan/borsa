<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $symbol
 * @property string $base_asset
 * @property string $quote_asset
 * @property string $maker_fee
 * @property string $taker_fee
 * @property string $min_order_size
 * @property int $price_scale
 * @property int $size_scale
 * @property string|null $last_price
 * @property bool $is_active
 */
class Market extends Model
{
    protected $fillable = [
        'symbol', 'base_asset', 'quote_asset', 'maker_fee', 'taker_fee',
        'min_order_size', 'price_scale', 'size_scale', 'last_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'maker_fee' => 'decimal:6',
            'taker_fee' => 'decimal:6',
            'min_order_size' => 'decimal:18',
            'last_price' => 'decimal:18',
            'price_scale' => 'integer',
            'size_scale' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Trade, $this>
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }
}
