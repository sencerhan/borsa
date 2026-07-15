<?php

namespace App\Exceptions;

class OrderNotFoundException extends DomainException
{
    public int $status = 404;

    public static function make(): self
    {
        return new self(__('exchange.errors.order_not_found'));
    }
}
