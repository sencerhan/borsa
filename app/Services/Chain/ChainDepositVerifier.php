<?php

namespace App\Services\Chain;

/**
 * Bir zincirden, verilen adrese gelen onaylı transferleri getiren doğrulayıcı.
 * Her ağ (BitKuruş, Tron/TRC20) için ayrı bir implementasyon vardır.
 */
interface ChainDepositVerifier
{
    /**
     * Adrese gelen onaylı transferleri döndürür.
     *
     * @return array<int, IncomingTransfer>
     */
    public function fetchIncoming(string $address): array;
}
