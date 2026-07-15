<?php

namespace App\Enums;

enum OrderType: string
{
    case Limit = 'limit';
    case Market = 'market';

    public function label(): string
    {
        return $this === self::Limit ? __('exchange.type.limit') : __('exchange.type.market');
    }
}
