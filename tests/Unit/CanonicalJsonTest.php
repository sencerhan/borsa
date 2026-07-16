<?php

namespace Tests\Unit;

use App\Support\CanonicalJson;
use PHPUnit\Framework\TestCase;

class CanonicalJsonTest extends TestCase
{
    public function test_object_keys_sorted_ascending(): void
    {
        $this->assertSame('{"a":2,"b":1}', CanonicalJson::encode(['b' => 1, 'a' => 2]));
    }

    public function test_nested_object_keys_sorted(): void
    {
        $this->assertSame(
            '{"x":{"y":2,"z":1}}',
            CanonicalJson::encode(['x' => ['z' => 1, 'y' => 2]]),
        );
    }

    public function test_array_order_preserved(): void
    {
        $this->assertSame('{"list":[3,1,2]}', CanonicalJson::encode(['list' => [3, 1, 2]]));
    }

    public function test_slashes_and_unicode_not_escaped(): void
    {
        $this->assertSame('{"u":"a/b"}', CanonicalJson::encode(['u' => 'a/b']));
        $this->assertSame('{"t":"çğ"}', CanonicalJson::encode(['t' => 'çğ']));
    }

    public function test_decimal_string_amounts_kept_as_strings(): void
    {
        $this->assertSame('{"v":"1.500000"}', CanonicalJson::encode(['v' => '1.500000']));
    }

    public function test_realistic_transfer_payload_matches_signing_order(): void
    {
        $payload = [
            'type' => 'transfer',
            'sender' => 'aa',
            'inputs' => [['version' => 1, 'token_id' => 't']],
            'outputs' => [['value' => '5.000000000000000000', 'token_id' => 'o', 'owner' => 'bb']],
            'nonce' => 'n',
            'tx_id' => 'x',
        ];

        $this->assertSame(
            '{"inputs":[{"token_id":"t","version":1}],"nonce":"n",'
            .'"outputs":[{"owner":"bb","token_id":"o","value":"5.000000000000000000"}],'
            .'"sender":"aa","tx_id":"x","type":"transfer"}',
            CanonicalJson::encode($payload),
        );
    }
}
