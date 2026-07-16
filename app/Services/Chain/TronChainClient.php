<?php

namespace App\Services\Chain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * TronGrid full-node HTTP istemcisi: TRC20 işlem kurma ve yayınlama.
 *
 * @see https://developers.tron.network/reference/trigger-smart-contract
 */
class TronChainClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * TRC20 transfer için imzasız işlem oluşturur (triggersmartcontract).
     *
     * @return array<string, mixed>|null  Oluşan transaction nesnesi (txID + raw_data) ya da null
     */
    public function buildTransfer(string $ownerHex, string $contractHex, string $paramHex, int $feeLimit): ?array
    {
        $response = $this->request()->post($this->url('/wallet/triggersmartcontract'), [
            'owner_address' => $ownerHex,
            'contract_address' => $contractHex,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => $paramHex,
            'fee_limit' => $feeLimit,
            'call_value' => 0,
            'visible' => false,
        ]);

        $tx = $response->json('transaction');

        // result.result true değilse (ör. hesap yok / revert) işlem kurulmaz.
        if (! is_array($tx) || ! isset($tx['txID'])) {
            return null;
        }

        return $tx;
    }

    /**
     * İmzalı işlemi yayınlar.
     *
     * @param  array<string, mixed>  $signedTx
     * @return array{ok: bool, txid: ?string, message: string}
     */
    public function broadcast(array $signedTx): array
    {
        $response = $this->request()->post($this->url('/wallet/broadcasttransaction'), $signedTx);

        $ok = $response->json('result') === true;
        $message = $response->json('message');

        // TronGrid hata mesajı hex kodlu gelebilir.
        if ($message !== null && ctype_xdigit($message)) {
            $message = hex2bin($message) ?: $message;
        }

        return [
            'ok' => $ok,
            'txid' => $response->json('txid'),
            'message' => $ok ? '' : ($message ?? $response->json('code') ?? 'yayınlama başarısız'),
        ];
    }

    /**
     * İşlem durumunu döndürür: success | failed | pending (henüz blokta değil / bulunamadı).
     */
    public function txStatus(string $txId): string
    {
        $response = $this->request()->post($this->url('/wallet/gettransactioninfobyid'), [
            'value' => $txId,
        ]);

        $receipt = $response->json('receipt.result');

        if ($receipt === null) {
            // Boş yanıt: işlem henüz bir bloğa girmemiş (veya bulunamadı).
            return 'pending';
        }

        return $receipt === 'SUCCESS' ? 'success' : 'failed';
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->timeout(20);

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $this->apiKey]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
