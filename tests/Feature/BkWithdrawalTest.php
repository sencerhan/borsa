<?php

namespace Tests\Feature;

use App\Services\Chain\BkChainClient;
use App\Services\Chain\BkWithdrawalService;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private string $sender = '';

    private string $recipient = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = str_repeat('a', 64);
        $this->recipient = str_repeat('b', 64);
    }

    private function service(): BkWithdrawalService
    {
        return new BkWithdrawalService(new BkChainClient('https://bitkurush.org'));
    }

    /** Zincir cevaplarını taklit eder: verilen tokenlar, submit 202, status committed. */
    private function fakeChain(array $tokens, ?array $afterMergeTokens = null): void
    {
        $walletCall = 0;
        Http::fake([
            'bitkurush.org/api/wallet/*' => function () use (&$walletCall, $tokens, $afterMergeTokens) {
                $walletCall++;
                // İlk çağrı: mevcut tokenlar. Merge sonrası ikinci çağrı: birleşik token.
                $set = ($walletCall === 1 || $afterMergeTokens === null) ? $tokens : $afterMergeTokens;

                return Http::response(['result' => 'ok', 'data' => ['tokens' => $set]]);
            },
            'bitkurush.org/api/tx/submit' => Http::response(['result' => 'accepted'], 202),
            'bitkurush.org/api/tx/*' => Http::response(['data' => ['status' => 'committed']]),
        ]);
    }

    private function token(string $id, string $value, int $version = 1): array
    {
        return ['token_id' => $id, 'value' => $value, 'status' => 'active', 'version' => $version];
    }

    /** Gönderilen submit payload'larını (imza öncesi alanlar) döndürür. */
    private function submittedPayloads(): array
    {
        $payloads = [];
        foreach (Http::recorded() as [$request]) {
            if (str_ends_with($request->url(), '/api/tx/submit')) {
                $payloads[] = $request->data();
            }
        }

        return $payloads;
    }

    public function test_transfer_when_single_utxo_matches_amount_exactly(): void
    {
        $this->fakeChain([$this->token('tok-a', '5.000000000000000000')]);

        $txId = $this->service()->send($this->sender, str_repeat('0', 128), $this->recipient, '5');

        $this->assertStringStartsWith('wallet-tx-', $txId);
        $payloads = $this->submittedPayloads();
        $this->assertCount(1, $payloads);
        $tx = $payloads[0];

        $this->assertSame('transfer', $tx['type']);
        $this->assertCount(1, $tx['inputs']);
        $this->assertSame('tok-a', $tx['inputs'][0]['token_id']);
        $this->assertCount(1, $tx['outputs']);
        $this->assertSame($this->recipient, $tx['outputs'][0]['owner']);
        $this->assertSame('5.000000000000000000', $tx['outputs'][0]['value']);
        // Değer korunumu: Σinput = Σoutput
        $this->assertSame(0, bccomp('5.000000000000000000', $tx['outputs'][0]['value'], 18));
    }

    public function test_split_when_single_utxo_exceeds_amount(): void
    {
        $this->fakeChain([$this->token('tok-big', '10.000000000000000000')]);

        $this->service()->send($this->sender, str_repeat('0', 128), $this->recipient, '3');

        $tx = $this->submittedPayloads()[0];
        $this->assertSame('split', $tx['type']);
        $this->assertCount(2, $tx['outputs']);

        // Alıcı 3, gönderici para üstü 7
        $recv = collect($tx['outputs'])->firstWhere('owner', $this->recipient);
        $change = collect($tx['outputs'])->firstWhere('owner', $this->sender);
        $this->assertSame('3.000000000000000000', $recv['value']);
        $this->assertSame('7.000000000000000000', $change['value']);

        // Değer korunumu: input (10) = alıcı (3) + change (7)
        $sumOut = bcadd($recv['value'], $change['value'], 18);
        $this->assertSame(0, bccomp('10.000000000000000000', $sumOut, 18));
    }

    public function test_merge_then_split_when_multiple_utxos_needed(): void
    {
        // İki token da tek başına 8'i karşılamıyor → önce merge, sonra split.
        // Merge sonrası birleşik token'ı yakalamak için: submit edilen merge
        // output token_id'sini kullanan bir wallet cevabı üret.
        $merged = null;
        Http::fake([
            'bitkurush.org/api/wallet/*' => function () use (&$merged) {
                if ($merged === null) {
                    return Http::response(['result' => 'ok', 'data' => ['tokens' => [
                        ['token_id' => 'tok-1', 'value' => '5.000000000000000000', 'status' => 'active', 'version' => 1],
                        ['token_id' => 'tok-2', 'value' => '4.000000000000000000', 'status' => 'active', 'version' => 1],
                    ]]]);
                }

                return Http::response(['result' => 'ok', 'data' => ['tokens' => [
                    ['token_id' => $merged, 'value' => '9.000000000000000000', 'status' => 'active', 'version' => 2],
                ]]]);
            },
            'bitkurush.org/api/tx/submit' => function ($request) use (&$merged) {
                if ($request->data()['type'] === 'merge') {
                    $merged = $request->data()['outputs'][0]['token_id'];
                }

                return Http::response(['result' => 'accepted'], 202);
            },
            'bitkurush.org/api/tx/*' => Http::response(['data' => ['status' => 'committed']]),
        ]);

        $this->service()->send($this->sender, str_repeat('0', 128), $this->recipient, '8');

        $payloads = $this->submittedPayloads();
        $this->assertCount(2, $payloads, 'merge + split iki işlem beklenir');

        // 1) merge: iki input → tek output (göndericiye), toplam 9
        $mergeTx = $payloads[0];
        $this->assertSame('merge', $mergeTx['type']);
        $this->assertCount(2, $mergeTx['inputs']);
        $this->assertSame('9.000000000000000000', $mergeTx['outputs'][0]['value']);
        $this->assertSame($this->sender, $mergeTx['outputs'][0]['owner']);

        // 2) split: birleşik 9'dan alıcıya 8, change 1
        $splitTx = $payloads[1];
        $this->assertSame('split', $splitTx['type']);
        $recv = collect($splitTx['outputs'])->firstWhere('owner', $this->recipient);
        $change = collect($splitTx['outputs'])->firstWhere('owner', $this->sender);
        $this->assertSame('8.000000000000000000', $recv['value']);
        $this->assertSame('1.000000000000000000', $change['value']);
    }

    public function test_throws_when_insufficient_onchain_balance(): void
    {
        $this->fakeChain([$this->token('tok-small', '2.000000000000000000')]);

        $this->expectException(\App\Exceptions\WithdrawalFailedException::class);
        $this->service()->send($this->sender, str_repeat('0', 128), $this->recipient, '5');
    }

    public function test_canonical_json_sorts_keys_and_keeps_array_order(): void
    {
        $json = CanonicalJson::encode([
            'type' => 'transfer',
            'sender' => 'x',
            'inputs' => [['version' => 1, 'token_id' => 't']],
        ]);

        // Üst anahtarlar sıralı (inputs, sender, type); iç nesne sıralı (token_id, version)
        $this->assertSame('{"inputs":[{"token_id":"t","version":1}],"sender":"x","type":"transfer"}', $json);
    }
}
