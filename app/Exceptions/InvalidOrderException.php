<?php

namespace App\Exceptions;

class InvalidOrderException extends DomainException
{
    public static function make(string $message): self
    {
        return new self($message);
    }

    public static function belowMinimum(string $minimum): self
    {
        return new self(__('exchange.errors.below_minimum', ['minimum' => $minimum]));
    }

    public static function emptyBook(): self
    {
        return new self(__('exchange.errors.empty_book'));
    }
}
