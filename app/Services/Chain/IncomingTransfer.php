<?php

namespace App\Services\Chain;

/**
 * Bir zincir adresine gelen (onaylı) transferin normalize edilmiş hâli.
 * Farklı zincirlerin (BitKuruş, Tron) yanıtları bu ortak yapıya indirgenir.
 */
final readonly class IncomingTransfer
{
    public function __construct(
        /** Zincir işlem kimliği — yatırma tekilliği (dedup) bunun üzerinden yapılır. */
        public string $txid,
        /** Ondalık string biçiminde gelen miktar (ör. "125.500000"). */
        public string $amount,
        /** Unix zaman damgası (sn), varsa. */
        public ?int $timestamp = null,
    ) {}
}
