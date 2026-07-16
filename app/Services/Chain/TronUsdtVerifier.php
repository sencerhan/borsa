<?php

namespace App\Services\Chain;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * USDT (TRC20) yatırma doğrulayıcı.
 * TronGrid API'sinden adrese gelen, doğrulanmış (only_confirmed) TRC20
 * USDT transferlerini okur. Yalnızca hedef adresi bizim adres olan ve
 * sözleşmesi USDT olan transferler yatırma sayılır.
 *
 * @see https://developers.tron.network/reference/get-trc20-transaction-info-by-account-address
 */
class TronUsdtVerifier implements ChainDepositVerifier
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $usdtContract,
        private readonly ?string $apiKey = null,
        private readonly int $limit = 50,
    ) {}

    public function fetchIncoming(string $address): array
    {
        $request = Http::acceptJson()->timeout(15);

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $this->apiKey]);
        }

        $response = $request->get(
            rtrim($this->baseUrl, '/')."/v1/accounts/{$address}/transactions/trc20",
            [
                'only_to' => 'true',
                'only_confirmed' => 'true',
                'contract_address' => $this->usdtContract,
                'limit' => $this->limit,
            ]
        );

        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('TronGrid TRC20 sorgusu başarısız', [
                'address' => $address,
                'status' => $response->status(),
            ]);

            return [];
        }

        $transfers = [];

        foreach ((array) $response->json('data', []) as $tx) {
            // Güvenlik: yalnızca bize gelen ve doğru USDT sözleşmesinden.
            if (($tx['to'] ?? null) !== $address) {
                continue;
            }

            if (($tx['token_info']['address'] ?? null) !== $this->usdtContract) {
                continue;
            }

            $txid = $tx['transaction_id'] ?? null;
            $rawValue = $tx['value'] ?? null;

            if ($txid === null || $rawValue === null) {
                continue;
            }

            $decimals = (int) ($tx['token_info']['decimals'] ?? 6);

            $transfers[] = new IncomingTransfer(
                txid: (string) $txid,
                amount: $this->formatUnits((string) $rawValue, $decimals),
                timestamp: isset($tx['block_timestamp']) ? (int) ($tx['block_timestamp'] / 1000) : null,
            );
        }

        return $transfers;
    }

    /**
     * Tam sayı ham değeri (ör. "1500000") ondalık string'e çevirir (ör. "1.500000").
     */
    private function formatUnits(string $value, int $decimals): string
    {
        if ($decimals <= 0) {
            return $value;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim(ltrim($value, '-'), '0');
        $digits = str_pad($digits === '' ? '0' : $digits, $decimals + 1, '0', STR_PAD_LEFT);

        $whole = substr($digits, 0, -$decimals);
        $fraction = substr($digits, -$decimals);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }
}
