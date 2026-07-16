# BitKuruş Borsa

> **TR** · [English below ↓](#bitkuruş-exchange-english)

Order-book tabanlı, **gerçek zincir entegrasyonlu** bir kripto para borsası: **BitKuruş (BK$)** ve **USDT (TRC20)** için gerçek yatırma/çekme, eşleştirme motoru ve otonom çalışan altyapı. Laravel + Vue/Inertia ile yazılmıştır.

<p>
  <img alt="Tests" src="https://img.shields.io/badge/tests-87%20passing-brightgreen">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3%20|%208.4%20|%208.5-777bb4">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-13-ff2d20">
  <img alt="License" src="https://img.shields.io/badge/license-MIT-blue">
</p>

## ⚠️ Sorumluluk Reddi

Bu proje **eğitim ve referans** amaçlıdır. **Bağımsız bir güvenlik denetiminden geçmemiştir.**
Gerçek fonlarla (kripto para, özel anahtarlar, hot-wallet) çalıştırmak **tamamen sizin
sorumluluğunuzdadır**. Özel anahtar yönetimi, custody ve fon güvenliği risklerini anlamadan
canlı ortamda kullanmayın. Yazarlar hiçbir kayıptan sorumlu değildir (bkz. [LICENSE](LICENSE)).

## Öne çıkan özellikler

- **Gerçek zincir yatırma/çekme**
  - **BK$ (BitKuruş):** Ed25519 (PHP `sodium`), UTXO modeli (transfer/split/merge), canonical-JSON imzalama, [bitkurush.org](https://bitkurush.org/docs) düğüm API'si.
  - **USDT (TRC20):** TronGrid ile yatırma tespiti; secp256k1 imzalı gerçek TRC20 `transfer` çekimi (Node yardımcısı `scripts/tron-signer.cjs`).
- **Eşleştirme motoru** — fiyat-zaman önceliği, limit + market emirler, kısmi dolum, maker/taker komisyonu, **self-trade koruması (STP)**.
- **Para güvenliği** — tüm bakiye mutasyonları `DB::transaction` + `lockForUpdate`, çift-defter `ledger_entries` kaydı.
- **Çekim mutabakatı** — çekimler async işlenir, `Processing` claim edilir, ödeme yayınlanmadan önce txid kaydedilir; yarıda kalan çekimler zincir durumuna göre **otomatik tamamlanır veya iade edilir**.
- **Otonom daemon** — yatırma tarama, çekim mutabakatı, piyasa yapıcı bot, kuyruk ve canlı yayın tek süreçte (`php artisan borsa:daemon`).
- **Canlı arayüz** — Reverb (WebSocket) ile anlık order book / trade / cüzdan güncellemeleri.
- **Çift dil** — Türkçe + İngilizce (tr/en).

## Ekran görüntüleri

<!-- docs/screenshots/ altına görseller ekleyip buraya bağlayın -->
_Yakında — `docs/screenshots/` klasörüne eklenecek._

## Teknoloji yığını

| Katman | Teknoloji |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia 3, TypeScript, Tailwind |
| Gerçek zamanlı | Laravel Reverb (WebSocket) |
| Kripto | `sodium` (Ed25519), Node `elliptic` + `js-sha3` (secp256k1/keccak) |
| Test/Kalite | PHPUnit, Larastan (level 7), Pint, ESLint, Prettier |

## Gereksinimler

- PHP 8.3+ (`ext-sodium`, `ext-bcmath`), Composer
- Node.js 22+ ve npm
- SQLite (varsayılan) veya MySQL/PostgreSQL

## Hızlı başlangıç

```bash
git clone <repo-url> borsa && cd borsa

# Tek komutla kurulum (composer install, .env, key, migrate, npm build)
composer setup

# Ortam değişkenlerini doldurun (bkz. .env.example yorumları)
#   - Reverb: php artisan reverb:install
#   - Zincir: BITKURUS_API_URL, TRONGRID_API_KEY, TRON_HOT_WALLET_* (bkz. docs/CHAIN_INTEGRATION.md)

# Geliştirme (Vite + Reverb + otonom daemon birlikte)
php artisan dev
```

Örnek veriler ve test kullanıcıları için: `php artisan db:seed`
(admin@example.com / test@example.com / maker@example.com).

## Zincir kurulumu

Gerçek yatırma/çekmeyi etkinleştirmek için zincir yapılandırması gerekir (USDT için TronGrid
API anahtarı + TRX ile fonlanmış hot-wallet). Ayrıntılar: **[docs/CHAIN_INTEGRATION.md](docs/CHAIN_INTEGRATION.md)**.

## Mimari

İş mantığı yalnızca `app/Services/` katmanındadır; controller'lar incedir. Eşleştirme motoru,
para güvenliği, yatırma doğrulama ve çekim mutabakatı: **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.
Kurulum/dağıtım (systemd, nginx): **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

## Test & kalite

```bash
composer test          # Pint + PHPStan + tüm testler (kalite kapısı)
php artisan test       # yalnızca testler
composer types:check   # PHPStan (Larastan level 7)
composer ci:check      # frontend + backend tüm kalite kontrolleri
npm run lint && npm run types:check
```

## Katkı

Katkılar memnuniyetle karşılanır — [CONTRIBUTING.md](CONTRIBUTING.md). Güvenlik açıkları için
[SECURITY.md](SECURITY.md).

## Lisans

[MIT](LICENSE) © 2026 Sencerhan

---

<a name="bitkuruş-exchange-english"></a>

# BitKuruş Exchange (English)

> **EN** · [Türkçe yukarıda ↑](#bitkuruş-borsa)

An order-book crypto exchange with **real on-chain integration**: genuine deposits/withdrawals
for **BitKuruş (BK$)** and **USDT (TRC20)**, a matching engine, and an autonomous backend.
Built with Laravel + Vue/Inertia.

## ⚠️ Disclaimer

This project is for **education and reference**. It has **not undergone an independent security
audit**. Running it with real funds (crypto, private keys, hot wallets) is **entirely at your own
risk**. Do not use in production without understanding private-key management, custody, and
fund-safety risks. The authors are not liable for any loss (see [LICENSE](LICENSE)).

## Highlights

- **Real on-chain deposits/withdrawals**
  - **BK$ (BitKuruş):** Ed25519 (PHP `sodium`), UTXO model (transfer/split/merge), canonical-JSON
    signing, [bitkurush.org](https://bitkurush.org/docs) node API.
  - **USDT (TRC20):** deposit detection via TronGrid; real signed TRC20 `transfer` withdrawals via
    secp256k1 (Node helper `scripts/tron-signer.cjs`).
- **Matching engine** — price-time priority, limit + market orders, partial fills, maker/taker
  fees, **self-trade prevention (STP)**.
- **Money safety** — every balance mutation runs in `DB::transaction` + `lockForUpdate`, with a
  double-entry `ledger_entries` record.
- **Withdrawal reconciliation** — withdrawals are processed asynchronously, claimed as `Processing`,
  and the txid is recorded before broadcast; stuck withdrawals are **auto-completed or refunded**
  based on on-chain status.
- **Autonomous daemon** — deposit scanning, withdrawal reconciliation, market-maker bot, queue and
  live broadcasting in one process (`php artisan borsa:daemon`).
- **Live UI** — instant order book / trade / wallet updates over Reverb (WebSocket).
- **Bilingual** — Turkish + English.

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia 3, TypeScript, Tailwind |
| Realtime | Laravel Reverb (WebSocket) |
| Crypto | `sodium` (Ed25519), Node `elliptic` + `js-sha3` (secp256k1/keccak) |
| Test/Quality | PHPUnit, Larastan (level 7), Pint, ESLint, Prettier |

## Requirements

- PHP 8.3+ (`ext-sodium`, `ext-bcmath`), Composer
- Node.js 22+ and npm
- SQLite (default) or MySQL/PostgreSQL

## Quick start

```bash
git clone <repo-url> borsa && cd borsa

# One-shot setup (composer install, .env, key, migrate, npm build)
composer setup

# Fill in env vars (see comments in .env.example)
#   - Reverb: php artisan reverb:install
#   - Chain: BITKURUS_API_URL, TRONGRID_API_KEY, TRON_HOT_WALLET_* (see docs/CHAIN_INTEGRATION.md)

# Development (Vite + Reverb + autonomous daemon together)
php artisan dev
```

Seed sample data & test users with `php artisan db:seed`.

## Docs

- **Chain setup:** [docs/CHAIN_INTEGRATION.md](docs/CHAIN_INTEGRATION.md)
- **Architecture:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Deployment (systemd, nginx):** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

## Testing & quality

```bash
composer test        # Pint + PHPStan + all tests (quality gate)
php artisan test     # tests only
npm run lint && npm run types:check
```

## Contributing

Contributions welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). For vulnerabilities, see
[SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE) © 2026 Sencerhan
