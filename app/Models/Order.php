<?php

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $market_id
 * @property OrderSide $side
 * @property OrderType $type
 * @property OrderStatus $status
 * @property string|null $price
 * @property string $quantity
 * @property string $filled_quantity
 * @property string|null $quote_amount
 * @property string $quote_filled
 * @property string|null $locked_asset
 * @property string $locked_amount
 */
class Order extends Model
{
    protected $fillable = [
        'user_id', 'market_id', 'side', 'type', 'status', 'price',
        'quantity', 'filled_quantity', 'quote_amount', 'quote_filled',
        'locked_asset', 'locked_amount',
    ];

    protected function casts(): array
    {
        return [
            'side' => OrderSide::class,
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'price' => 'decimal:18',
            'quantity' => 'decimal:18',
            'filled_quantity' => 'decimal:18',
            'quote_amount' => 'decimal:18',
            'quote_filled' => 'decimal:18',
            'locked_amount' => 'decimal:18',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * Defterde aktif (eşleşmeye açık) emirler.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Open->value, OrderStatus::PartiallyFilled->value]);
    }
}
