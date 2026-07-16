<?php

namespace Tests\Feature;

use App\Services\Chain\TronChainClient;
use App\Services\Chain\TronWithdrawalService;
use App\Support\TronCrypto;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TronWithdrawalTest extends TestCase
{
    private function service(): TronWithdrawalService
    {
        return new TronWithdrawalService(
            new TronChainClient('https://api.trongrid.io', null),
            'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            20000000,
            6,
        );
    }

    public function test_builds_correct_trc20_transfer_and_broadcasts(): void
    {
        $hot = TronCrypto::generate();
        $recipient = TronCrypto::generate()['address_base58'];

        Http::fake([
            'api.trongrid.io/wallet/triggersmartcontract' => Http::response([
                'result' => ['result' => true],
                'transaction' => ['txID' => str_repeat('a', 64), 'raw_data' => ['x' => 1]],
            ]),
            'api.trongrid.io/wallet/broadcasttransaction' => Http::response([
                'result' => true,
                'txid' => str_repeat('a', 64),
            ]),
        ]);

        $txid = $this->service()->send($hot['private_key'], $hot['address_base58'], $recipient, '1.5');

        $this->assertSame(str_repeat('a', 64), $txid);

        // triggersmartcontract isteğindeki ABI parametresini incele
        $trigger = collect(Http::recorded())->first(
            fn ($pair) => str_ends_with($pair[0]->url(), '/wallet/triggersmartcontract'),
        );
        $param = $trigger[0]->data()['parameter'];

        // 2 x 32 bayt = 128 hex
        $this->assertSame(128, strlen($param));

        // İlk 32 bayt: alıcı adresi (20 bayt, sola sıfır dolgulu)
        $recipientHex = substr(TronCrypto::toHex($recipient), 2); // 41 önekini at
        $this->assertSame(str_pad($recipientHex, 64, '0', STR_PAD_LEFT), substr($param, 0, 64));

        // İkinci 32 bayt: 1.5 USDT (6 hane) = 1_500_000 raw
        $this->assertSame(1500000, hexdec(substr($param, 64)));

        // broadcast imza içermeli
        $broadcast = collect(Http::recorded())->first(
            fn ($pair) => str_ends_with($pair[0]->url(), '/wallet/broadcasttransaction'),
        );
        $this->assertArrayHasKey('signature', $broadcast[0]->data());
        $this->assertSame(130, strlen($broadcast[0]->data()['signature'][0])); // 65 bayt
    }

    public function test_throws_when_broadcast_rejected(): void
    {
        $hot = TronCrypto::generate();
        $recipient = TronCrypto::generate()['address_base58'];

        Http::fake([
            'api.trongrid.io/wallet/triggersmartcontract' => Http::response([
                'result' => ['result' => true],
                'transaction' => ['txID' => str_repeat('b', 64), 'raw_data' => []],
            ]),
            'api.trongrid.io/wallet/broadcasttransaction' => Http::response([
                'result' => false,
                'code' => 'CONTRACT_VALIDATE_ERROR',
                'message' => bin2hex('balance is not sufficient'),
            ]),
        ]);

        $this->expectException(\App\Exceptions\WithdrawalFailedException::class);
        $this->service()->send($hot['private_key'], $hot['address_base58'], $recipient, '1');
    }
}
