<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case PartiallyFilled = 'partially_filled';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    /** Emir hâlâ defterde aktif mi (eşleşmeye açık)? */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::PartiallyFilled;
    }

    public function label(): string
    {
        return __('exchange.status.'.$this->value);
    }
}
