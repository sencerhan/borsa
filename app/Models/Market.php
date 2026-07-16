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
 * @property bool $bot_active
 * @property string $bot_reference_price
 * @property string $bot_volatility
 * @property string $bot_spread
 * @property string $bot_min_qty
 * @property string $bot_max_qty
 * @property int $bot_interval
 * @property bool $simulator_active
 * @property int $simulator_interval
 */
class Market extends Model
{
    protected $fillable = [
        'symbol', 'base_asset', 'quote_asset', 'maker_fee', 'taker_fee',
        'min_order_size', 'price_scale', 'size_scale', 'last_price', 'is_active',
        'bot_active', 'bot_reference_price', 'bot_volatility', 'bot_spread',
        'bot_min_qty', 'bot_max_qty', 'bot_interval', 'simulator_active', 'simulator_interval',
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
            'bot_active' => 'boolean',
            'bot_reference_price' => 'decimal:18',
            'bot_volatility' => 'decimal:6',
            'bot_spread' => 'decimal:6',
            'bot_min_qty' => 'decimal:18',
            'bot_max_qty' => 'decimal:18',
            'bot_interval' => 'integer',
            'simulator_active' => 'boolean',
            'simulator_interval' => 'integer',
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

    /**
     * Bu market için geçerli yayın (broadcast) kanal adı.
     * Sembol `$` ve `/` gibi Pusher/Reverb'de geçersiz karakterler içerdiğinden
     * kanal adı sadece alfanümerik hale getirilir (BK$/USDT -> market.BKUSDT).
     */
    public function broadcastChannel(): string
    {
        return 'market.'.self::channelSlug($this->symbol);
    }

    /**
     * Sembolü kanal-güvenli slug'a çevirir (frontend ile birebir aynı mantık).
     */
    public static function channelSlug(string $symbol): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $symbol) ?? '';
    }
}
