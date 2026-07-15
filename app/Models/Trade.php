<?php

namespace App\Models;

use App\Enums\OrderSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $market_id
 * @property int $buy_order_id
 * @property int $sell_order_id
 * @property int $buyer_id
 * @property int $seller_id
 * @property OrderSide $taker_side
 * @property string $price
 * @property string $quantity
 * @property string $quote_amount
 * @property string $buyer_fee
 * @property string $seller_fee
 */
class Trade extends Model
{
    protected $fillable = [
        'market_id', 'buy_order_id', 'sell_order_id', 'buyer_id', 'seller_id',
        'taker_side', 'price', 'quantity', 'quote_amount', 'buyer_fee', 'seller_fee',
    ];

    protected function casts(): array
    {
        return [
            'taker_side' => OrderSide::class,
            'price' => 'decimal:18',
            'quantity' => 'decimal:18',
            'quote_amount' => 'decimal:18',
            'buyer_fee' => 'decimal:18',
            'seller_fee' => 'decimal:18',
        ];
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
