<?php

namespace App\Services\Chain;

use App\Exceptions\WithdrawalFailedException;
use App\Support\BkKeypair;
use App\Support\CanonicalJson;

/**
 * BitKuruş (BK$) gerçek zincir çekimi.
 *
 * Gönderici adresinin UTXO'larından (token) yola çıkarak alıcıya para gönderir.
 * Değer korunumu (Σinput = Σoutput) ve doküman UTXO mantığına uyar:
 *  - transfer (1→1): tek UTXO tam tutarı karşılar
 *  - split (1→2): tek UTXO > tutar; alıcı + göndericiye para üstü (change)
 *  - merge (N→1): birden çok UTXO tek token'a birleşir (commit beklenir), sonra transfer/split
 *
 * @see https://bitkurush.org/docs
 */
class BkWithdrawalService
{
    /** Para tutarlarında kullanılan ondalık hane sayısı. */
    private const SCALE = 18;

    public function __construct(private readonly BkChainClient $client) {}

    /**
     * Alıcıya `amount` BK$ gönderir; onaylanan ödeme işleminin tx_id'sini döndürür.
     *
     * @throws WithdrawalFailedException
     */
    /**
     * @param  ?callable  $onPaymentTxId  Ödeme (transfer/split) yayınlanmadan hemen önce
     *                                     tx_id ile çağrılır (mutabakat için kayıt).
     */
    public function send(string $senderAddress, string $secretHex, string $recipient, string $amount, ?callable $onPaymentTxId = null): string
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $recipient)) {
            throw WithdrawalFailedException::make('Geçersiz BK$ alıcı adresi.');
        }

        $amount = $this->money($amount);

        // 1) Gönderilebilir token'ları büyükten küçüğe sırala, greedy seç.
        $tokens = $this->client->activeTokens($senderAddress);
        usort($tokens, fn ($a, $b) => bccomp($b['value'], $a['value'], self::SCALE));

        $selected = [];
        $sum = '0';
        foreach ($tokens as $token) {
            $selected[] = $token;
            $sum = bcadd($sum, $token['value'], self::SCALE);
            if (bccomp($sum, $amount, self::SCALE) >= 0) {
                break;
            }
        }

        if (bccomp($sum, $amount, self::SCALE) < 0) {
            throw WithdrawalFailedException::make('Zincirde yeterli bakiye yok (harcanabilir UTXO).');
        }

        // 2) Birden çok input gerekiyorsa önce merge et, tek token'a indir.
        if (count($selected) > 1) {
            $merged = $this->merge($selected, $senderAddress, $secretHex, $sum);
            $input = $merged; // {token_id, value, version}
        } else {
            $input = $selected[0];
        }

        // 3) Tek input'tan öde: tam eşitse transfer, fazlaysa split (change geri döner).
        return $this->payFromSingle($input, $senderAddress, $secretHex, $recipient, $amount, $onPaymentTxId);
    }

    /** Tek input'tan ödeme: transfer (tam) ya da split (change'li). */
    private function payFromSingle(array $input, string $sender, string $secret, string $recipient, string $amount, ?callable $onTxId): string
    {
        if (bccomp($input['value'], $amount, self::SCALE) === 0) {
            // transfer (1→1)
            $outputs = [
                ['owner' => $recipient, 'token_id' => $this->newTokenId(), 'value' => $input['value']],
            ];

            return $this->submitAndWait('transfer', $this->inputRef($input), $outputs, $sender, $secret, $onTxId);
        }

        // split (1→2): alıcı + göndericiye para üstü
        $change = bcsub($input['value'], $amount, self::SCALE);
        $outputs = [
            ['owner' => $recipient, 'token_id' => $this->newTokenId(), 'value' => $amount],
            ['owner' => $sender, 'token_id' => $this->newTokenId(), 'value' => $change],
        ];

        return $this->submitAndWait('split', $this->inputRef($input), $outputs, $sender, $secret, $onTxId);
    }

    /**
     * Birden çok token'ı tek token'a birleştirir (göndericiye), commit bekler ve
     * yeni (merged) token'ı version'ıyla döndürür.
     *
     * @return array{token_id: string, value: string, version: int}
     */
    private function merge(array $inputs, string $sender, string $secret, string $totalValue): array
    {
        $mergedTokenId = $this->newTokenId();
        $outputs = [
            ['owner' => $sender, 'token_id' => $mergedTokenId, 'value' => $totalValue],
        ];

        $this->submitAndWait('merge', $this->inputRef($inputs), $outputs, $sender, $secret);

        // Merge sonrası cüzdanı tazele; yeni token'ın güncel version'ını al.
        foreach ($this->client->activeTokens($sender) as $token) {
            if ($token['token_id'] === $mergedTokenId) {
                return $token;
            }
        }

        throw WithdrawalFailedException::make('Merge sonrası birleşik token bulunamadı.');
    }

    /** Payload'ı imzalar, gönderir ve committed olana kadar bekler; tx_id döndürür. */
    private function submitAndWait(string $type, array $inputs, array $outputs, string $sender, string $secret, ?callable $onTxId = null): string
    {
        $txId = $this->newTxId();

        $payload = [
            'inputs' => $inputs,
            'nonce' => $txId.'-nonce',
            'outputs' => $outputs,
            'sender' => $sender,
            'tx_id' => $txId,
            'type' => $type,
        ];

        // Göndericiye geri dönen output'lar (split change / merge çıktısı) borsanın kendi
        // token'ıdır; deposit adresine döner ama yatırma değildir. Deposit tarayıcısı
        // tekrar kredilemesin diye dahili olarak işaretle.
        foreach ($outputs as $output) {
            if ($output['owner'] === $sender) {
                \App\Models\ChainInternalToken::mark('BK$', $output['token_id']);
            }
        }

        // İmza: canonical JSON'ın Ed25519 imzası (signature alanı hariç).
        $signature = BkKeypair::sign(CanonicalJson::encode($payload), $secret);

        // Ödeme yayınlanmadan hemen önce tx_id'yi bildir (mutabakat kaydı için).
        if ($onTxId !== null) {
            $onTxId($txId);
        }

        $result = $this->client->submit($payload + ['signature' => $signature]);

        // 202 kabul edildi; diğer her şey (409 vb.) reddedilmiştir.
        if ($result['status'] !== 202) {
            $body = $result['body'];
            $reason = $body['data']['error'] ?? $body['error'] ?? $body['reason'] ?? 'işlem reddedildi';
            throw WithdrawalFailedException::make("Zincir işlemi reddedildi: {$reason}");
        }

        if (! $this->client->waitForCommit($txId)) {
            throw WithdrawalFailedException::make('İşlem zincirde onaylanmadı (committed olmadı).');
        }

        return $txId;
    }

    /**
     * Input token(lar)ını zincirin beklediği {token_id, version} biçimine indirger.
     *
     * @return array<int, array{token_id: string, version: int}>
     */
    private function inputRef(array $tokenOrTokens): array
    {
        $tokens = isset($tokenOrTokens['token_id']) ? [$tokenOrTokens] : $tokenOrTokens;

        return array_map(
            fn ($t) => ['token_id' => $t['token_id'], 'version' => $t['version']],
            $tokens,
        );
    }

    private function money(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }

    private function newTokenId(): string
    {
        return 'wallet-out-'.bin2hex(random_bytes(8));
    }

    private function newTxId(): string
    {
        return 'wallet-tx-'.bin2hex(random_bytes(8));
    }
}
