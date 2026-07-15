<?php

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    /** Karşı taraf */
    public function opposite(): self
    {
        return $this === self::Buy ? self::Sell : self::Buy;
    }

    public function label(): string
    {
        return $this === self::Buy ? __('exchange.side.buy') : __('exchange.side.sell');
    }
}
