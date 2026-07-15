<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Deposit = 'deposit';
    case Withdraw = 'withdraw';
    case Trade = 'trade';
    case Fee = 'fee';
    case Lock = 'lock';
    case Unlock = 'unlock';
    case Refund = 'refund';
}
