<?php

namespace App\Exceptions;

use Exception;

/**
 * Tüm domain (iş kuralı) hatalarının temel sınıfı.
 */
abstract class DomainException extends Exception
{
    /** HTTP durum kodu */
    public int $status = 422;
}
