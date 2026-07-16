<?php

namespace App\Services\Chain;

use App\Exceptions\WithdrawalFailedException;
use App\Support\TronCrypto;

/**
 * USDT (TRC20) gerçek zincir çekimi.
 *
 * Çekimler, operatörün USDT + TRX (gas) ile fonladığı merkezi bir hot-wallet'tan
 * gönderilir. TRC20 `transfer(address,uint256)` çağrısı TronGrid ile kurulur,
 * txID secp256k1 ile imzalanır (Node yardımcısı) ve ağa yayınlanır.
 */
class TronWithdrawalService
{
    public function __construct(
        private readonly TronChainClient $client,
        private readonly string $usdtContractBase58,
        private readonly int $feeLimit,
        private readonly int $decimals = 6,
    ) {}

    /**
     * Hot-wallet'tan alıcıya USDT gönderir; yayınlanan işlemin txid'sini döndürür.
     *
     * @throws WithdrawalFailedException
     */
    /**
     * @param  ?callable  $onTxId  İşlem yayınlanmadan hemen önce txID ile çağrılır (mutabakat kaydı).
     */
    public function send(string $hotPrivHex, string $hotAddressBase58, string $recipientBase58, string $amount, ?callable $onTxId = null): string
    {
        $ownerHex = TronCrypto::toHex($hotAddressBase58);
        $contractHex = TronCrypto::toHex($this->usdtContractBase58);

        $param = $this->encodeTransferParams($recipientBase58, $amount);

        // 1) İmzasız işlemi TronGrid'de kur.
        $tx = $this->client->buildTransfer($ownerHex, $contractHex, $param, $this->feeLimit);

        if ($tx === null) {
            throw WithdrawalFailedException::make('Tron işlemi kurulamadı (adres/bakiye/energy?).');
        }

        // 2) txID'yi imzala (secp256k1) ve işleme ekle.
        $tx['signature'] = [TronCrypto::sign($hotPrivHex, $tx['txID'])];

        // 3) Yayınlamadan hemen önce txID'yi bildir (mutabakat kaydı için).
        if ($onTxId !== null) {
            $onTxId($tx['txID']);
        }

        // 4) Yayınla.
        $result = $this->client->broadcast($tx);

        if (! $result['ok']) {
            throw WithdrawalFailedException::make('Tron yayınlama reddedildi: '.$result['message']);
        }

        return $result['txid'] ?? $tx['txID'];
    }

    /**
     * transfer(address,uint256) parametrelerini ABI-encode eder:
     * 32 bayt alıcı (20 bayt adres, sola sıfır dolgulu) + 32 bayt tutar.
     */
    private function encodeTransferParams(string $recipientBase58, string $amount): string
    {
        // Alıcı: 41+20 bayt hex → 41 ön ekini at → 20 bayt (40 hex) → 64 hex'e sola dolgu.
        $recipientHex = substr(TronCrypto::toHex($recipientBase58), 2);
        $addressParam = str_pad($recipientHex, 64, '0', STR_PAD_LEFT);

        if (! is_numeric($amount)) {
            throw WithdrawalFailedException::make('Geçersiz USDT tutarı.');
        }

        // Tutar: ondalık → tam sayı ham (10^decimals) → hex → 64 hex'e sola dolgu.
        $raw = bcmul($amount, bcpow('10', (string) $this->decimals), 0);
        $amountParam = str_pad($this->dec2hex($raw), 64, '0', STR_PAD_LEFT);

        return $addressParam.$amountParam;
    }

    /**
     * Ondalık tam sayı string'ini hex'e çevirir (gmp'siz, bcmath ile).
     *
     * @param  numeric-string  $dec
     */
    private function dec2hex(string $dec): string
    {
        if (bccomp($dec, '0') <= 0) {
            return '0';
        }

        $hex = '';
        while (bccomp($dec, '0') > 0) {
            $hex = dechex((int) bcmod($dec, '16')).$hex;
            $dec = bcdiv($dec, '16', 0);
        }

        return $hex;
    }
}
