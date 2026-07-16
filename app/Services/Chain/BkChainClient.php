<?php

namespace App\Services\Chain;

use Illuminate\Support\Facades\Http;

/**
 * BitKuruş düğüm API istemcisi: cüzdan/token okuma, işlem gönderme ve durum sorgulama.
 *
 * @see https://bitkurush.org/docs
 */
class BkChainClient
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Adresin harcanabilir (active) token'larını version bilgisiyle döndürür.
     *
     * @return array<int, array{token_id: string, value: string, version: int}>
     */
    public function activeTokens(string $address): array
    {
        $response = Http::acceptJson()->timeout(15)
            ->get($this->url("/api/wallet/{$address}"));

        if (! $response->successful() || $response->json('result') !== 'ok') {
            return [];
        }

        $tokens = [];
        foreach ((array) $response->json('data.tokens', []) as $token) {
            if (($token['status'] ?? null) !== 'active') {
                continue;
            }

            $tokens[] = [
                'token_id' => (string) $token['token_id'],
                'value' => (string) $token['value'],
                'version' => (int) ($token['version'] ?? 1),
            ];
        }

        return $tokens;
    }

    /**
     * İmzalı işlemi gönderir.
     *
     * @param  array<string, mixed>  $signedTx
     * @return array{status: int, body: array<string, mixed>}
     */
    public function submit(array $signedTx): array
    {
        $response = Http::acceptJson()->timeout(20)
            ->post($this->url('/api/tx/submit'), $signedTx);

        return [
            'status' => $response->status(),
            'body' => (array) $response->json(),
        ];
    }

    /** İşlem durumunu döndürür: pending | committed | rejected | null (bulunamadı). */
    public function txStatus(string $txId): ?string
    {
        $response = Http::acceptJson()->timeout(15)
            ->get($this->url("/api/tx/{$txId}"));

        if (! $response->successful()) {
            return null;
        }

        return $response->json('data.status') ?? $response->json('status');
    }

    /**
     * İşlem committed olana kadar bekler. committed→true, rejected/zaman aşımı→false.
     */
    public function waitForCommit(string $txId, int $maxAttempts = 8, int $sleepMs = 1500): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $status = $this->txStatus($txId);

            if ($status === 'committed') {
                return true;
            }

            if ($status === 'rejected') {
                return false;
            }

            usleep($sleepMs * 1000);
        }

        return false;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
