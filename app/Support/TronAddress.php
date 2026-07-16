<?php

namespace App\Support;

/**
 * Tron (TRC20) adres yardımcıları.
 * Geçerli base58check biçiminde ("T..." ile başlayan, 34 karakter) adres üretir;
 * bu sayede TronGrid API'si adresi sorgulayabilir.
 *
 * Not: Adresin özel anahtarı bu uygulamada tutulmaz; fon yönetimi (sweep)
 * operatörün ayrı cüzdan altyapısının sorumluluğundadır.
 */
final class TronAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** 0x41 ön eki + 20 bayt = mainnet adres gövdesi. */
    private const PREFIX = "\x41";

    /** Rastgele, geçerli biçimde yeni bir Tron adresi üretir. */
    public static function generate(): string
    {
        $payload = self::PREFIX.random_bytes(20);

        return self::base58Check($payload);
    }

    /** 21 baytlık gövdeye 4 baytlık checksum ekleyip base58 kodlar. */
    private static function base58Check(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return self::base58Encode($payload.$checksum);
    }

    /** İkili veriyi base58 (Bitcoin/Tron alfabesi) ile kodlar. */
    private static function base58Encode(string $bytes): string
    {
        // Baştaki sıfır baytlar "1" olarak korunur.
        $zeros = 0;
        $length = strlen($bytes);
        while ($zeros < $length && $bytes[$zeros] === "\x00") {
            $zeros++;
        }

        // Büyük sayıyı 58 tabanına böl (bayt dizisi üzerinde uzun bölme).
        $digits = [0];
        for ($i = 0; $i < $length; $i++) {
            $carry = ord($bytes[$i]);
            foreach ($digits as $j => $digit) {
                $carry += $digit << 8;
                $digits[$j] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }

        $result = str_repeat('1', $zeros);
        for ($i = count($digits) - 1; $i >= 0; $i--) {
            $result .= self::ALPHABET[$digits[$i]];
        }

        return $result;
    }
}
