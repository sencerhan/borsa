<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Tron kripto işlemleri (secp256k1 + keccak). PHP'de gmp olmadığı için
 * imzalama/adres türetme Node yardımcısına (scripts/tron-signer.cjs) delege edilir.
 * Private key argv'de görünmesin diye girdi stdin'den JSON olarak verilir.
 */
final class TronCrypto
{
    /** @return array{private_key: string, address_hex: string, address_base58: string} */
    public static function generate(): array
    {
        return self::run(['cmd' => 'keygen']);
    }

    /** @return array{address_hex: string, address_base58: string} */
    public static function addressFromPrivate(string $privHex): array
    {
        return self::run(['cmd' => 'address', 'priv' => $privHex]);
    }

    /** base58 Tron adresini 41+20 bayt hex'e çevirir. */
    public static function toHex(string $base58): string
    {
        return self::run(['cmd' => 'tohex', 'addr' => $base58])['address_hex'];
    }

    /** txID (32 bayt hash hex) imzalar; 65 bayt (r||s||v) hex imza döndürür. */
    public static function sign(string $privHex, string $hashHex): string
    {
        return self::run(['cmd' => 'sign', 'priv' => $privHex, 'hash' => $hashHex])['signature'];
    }

    /**
     * @param  array<string, string>  $input
     * @return array<string, mixed>
     */
    private static function run(array $input): array
    {
        $node = (string) config('services.trongrid.node_bin', 'node');
        $script = base_path('scripts/tron-signer.cjs');

        $result = Process::timeout(20)
            ->input(json_encode($input, JSON_THROW_ON_ERROR))
            ->run([$node, $script]);

        if (! $result->successful()) {
            throw new RuntimeException('Tron kripto hatası: '.trim($result->errorOutput()));
        }

        return json_decode(trim($result->output()), true, 512, JSON_THROW_ON_ERROR);
    }
}
