<?php

namespace App\Exceptions;

/**
 * Zincir üzerinde çekim (para gönderimi) başarısız olduğunda fırlatılır.
 */
class WithdrawalFailedException extends DomainException
{
    public static function make(string $reason): self
    {
        return new self($reason);
    }
}
