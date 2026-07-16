<?php

namespace App\Support;

/**
 * BitKuruş (BK Chain) Ed25519 anahtar çifti yardımcıları.
 *
 * Adres = Ed25519 public key'in 64 karakter küçük harf hex hâli.
 * Özel anahtar (64 bayt) zincir işlemlerini imzalamak için kullanılır ve
 * uygulamada şifreli saklanır.
 */
final class BkKeypair
{
    /**
     * Yeni bir anahtar çifti üretir.
     *
     * @return array{address: string, secret: string} address=pubkey hex, secret=secret key hex
     */
    public static function generate(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            'address' => bin2hex(sodium_crypto_sign_publickey($keypair)),
            'secret' => bin2hex(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    /**
     * Verilen mesajı (canonical JSON) hex secret key ile Ed25519 imzalar.
     * 128 karakter küçük harf hex imza döndürür.
     */
    public static function sign(string $message, string $secretHex): string
    {
        $secret = sodium_hex2bin($secretHex);

        return bin2hex(sodium_crypto_sign_detached($message, $secret));
    }
}
