<?php

namespace App\Exceptions;

class MarketInactiveException extends DomainException
{
    public static function make(): self
    {
        return new self(__('exchange.errors.market_inactive'));
    }
}
