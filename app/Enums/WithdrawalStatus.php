<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing'; // zincire gönderiliyor (claim edilmiş)
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('exchange.transfer_status.'.$this->value);
    }
}
