<?php

namespace App\Exceptions;

class MarketNotFoundException extends DomainException
{
    public int $status = 404;

    public static function forSymbol(string $symbol): self
    {
        return new self(__('exchange.errors.market_not_found', ['symbol' => $symbol]));
    }
}
