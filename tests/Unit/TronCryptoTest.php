<?php

namespace Tests\Unit;

use App\Support\TronCrypto;
use Tests\TestCase;

/**
 * TronCrypto Node yardımcısına (scripts/tron-signer.cjs) delege eder;
 * bu testler `node` + `elliptic`/`js-sha3` gerektirir.
 */
class TronCryptoTest extends TestCase
{
    public function test_address_derivation_matches_known_vector(): void
    {
        // TronWeb kanonik test vektörü
        $result = TronCrypto::addressFromPrivate(
            'da146374a75310b9666e834ee4ad0866d6f4035967bfc76217c5a495fff9f0d0',
        );

        $this->assertSame('41928c9af0651632157ef27a2cf17ca72c575a4d21', $result['address_hex']);
        $this->assertStringStartsWith('T', $result['address_base58']);
    }

    public function test_generate_is_self_consistent(): void
    {
        $kp = TronCrypto::generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $kp['private_key']);
        $this->assertMatchesRegularExpression('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $kp['address_base58']);

        // Private key'den türetilen adres, üretilen adresle aynı olmalı
        $derived = TronCrypto::addressFromPrivate($kp['private_key']);
        $this->assertSame($kp['address_base58'], $derived['address_base58']);
    }

    public function test_tohex_round_trips_base58_address(): void
    {
        $kp = TronCrypto::generate();
        $this->assertSame($kp['address_hex'], TronCrypto::toHex($kp['address_base58']));
    }

    public function test_sign_returns_65_byte_signature(): void
    {
        $kp = TronCrypto::generate();
        $sig = TronCrypto::sign($kp['private_key'], str_repeat('a', 64));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{130}$/', $sig);
    }
}
