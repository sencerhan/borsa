<?php

namespace Tests\Unit;

use App\Support\BkKeypair;
use PHPUnit\Framework\TestCase;

class BkKeypairTest extends TestCase
{
    public function test_generate_produces_ed25519_address_and_secret(): void
    {
        $kp = BkKeypair::generate();

        // Adres = 64 karakter küçük harf hex (Ed25519 public key)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $kp['address']);
        // Secret key = 64 bayt = 128 hex
        $this->assertSame(128, strlen($kp['secret']));
    }

    public function test_signature_is_128_hex_and_verifies_against_address(): void
    {
        $kp = BkKeypair::generate();
        $message = 'canonical-json-payload';

        $signature = BkKeypair::sign($message, $kp['secret']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $signature);
        // İmza, adresteki public key ile doğrulanmalı
        $this->assertTrue(sodium_crypto_sign_verify_detached(
            sodium_hex2bin($signature),
            $message,
            sodium_hex2bin($kp['address']),
        ));
    }

    public function test_wrong_message_fails_verification(): void
    {
        $kp = BkKeypair::generate();
        $signature = BkKeypair::sign('original', $kp['secret']);

        $this->assertFalse(sodium_crypto_sign_verify_detached(
            sodium_hex2bin($signature),
            'tampered',
            sodium_hex2bin($kp['address']),
        ));
    }
}
