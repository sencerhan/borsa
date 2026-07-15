<?php

namespace App\Exceptions;

class InsufficientBalanceException extends DomainException
{
    public static function for(string $asset): self
    {
        return new self(__('exchange.errors.insufficient_balance', ['asset' => $asset]));
    }
}
