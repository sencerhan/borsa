<?php

namespace App\Services\Chain;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BitKuruş (BK$) yatırma doğrulayıcı.
 *
 * BitKuruş UTXO tabanlı bir zincirdir: adrese gelen her para, adresin sahip
 * olduğu ayrı bir "token" (UTXO) olarak durur. Gelen transfer aktivite akışında
 * `receive` yerine `split` gibi görünebildiği için doğrulama, aktivite tipine
 * DEĞİL, adresin aktif token'larına (gerçek bakiye kaynağı) dayanır.
 *
 * Her token'ın benzersiz `token_id`'si tekilliği (dedup) sağlar.
 *
 * @see https://bitkurush.org/docs
 */
class BkChainVerifier implements ChainDepositVerifier
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function fetchIncoming(string $address): array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->get(rtrim($this->baseUrl, '/')."/api/wallet/{$address}");

        if (! $response->successful() || $response->json('result') !== 'ok') {
            Log::warning('BK Chain cüzdan sorgusu başarısız', [
                'address' => $address,
                'status' => $response->status(),
            ]);

            return [];
        }

        $transfers = [];

        foreach ((array) $response->json('data.tokens', []) as $token) {
            // Yalnızca aktif (harcanmamış) token'lar adrese gelen bakiyedir.
            if (($token['status'] ?? null) !== 'active') {
                continue;
            }

            $tokenId = $token['token_id'] ?? null;
            $value = $token['value'] ?? null;

            if ($tokenId === null || $value === null) {
                continue;
            }

            $transfers[] = new IncomingTransfer(
                txid: (string) $tokenId,
                amount: (string) $value,
                timestamp: isset($token['created_at']) ? (strtotime((string) $token['created_at']) ?: null) : null,
            );
        }

        return $transfers;
    }
}
