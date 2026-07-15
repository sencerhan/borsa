<?php

namespace App\Services\Data;

use App\Enums\OrderSide;
use App\Enums\OrderType;

/**
 * Emir verme girdisi (tüm sayısal değerler string — BCMath için).
 */
final readonly class PlaceOrderData
{
    public function __construct(
        public int $marketId,
        public OrderSide $side,
        public OrderType $type,
        public ?string $price = null,       // limit için zorunlu
        public ?string $quantity = null,    // limit ve market-sell için (base)
        public ?string $quoteAmount = null, // market-buy için (quote bütçesi)
    ) {}
}
