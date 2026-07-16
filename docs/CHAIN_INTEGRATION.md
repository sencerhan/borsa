# Chain Integration / Zincir Entegrasyonu

> EN + kısa TR notları. How real deposits & withdrawals work and how to configure them.

> ⚠️ Custody / real funds. Read [SECURITY.md](../SECURITY.md) first. Running this with real funds
> is at your own risk; the software is unaudited.

## Overview

| Asset | Network | Deposit detection | Withdrawal signing |
| --- | --- | --- | --- |
| **BK$** | BitKuruş (Ed25519, UTXO) | node API `GET /api/wallet/{addr}` tokens | `sodium` Ed25519 in PHP |
| **USDT** | Tron (TRC20, account) | TronGrid TRC20 transfers | secp256k1 via Node helper |

Deposit addresses are **exchange-controlled key pairs** (the private key is generated on wallet
creation and stored encrypted in `wallets.deposit_secret` via `Crypt::encryptString`). This is
what makes real, non-simulated withdrawals possible.

## Configuration

All chain settings live in `config/services.php` and are read from the environment. Copy
`.env.example` and fill these in:

| Env var | Purpose | Default |
| --- | --- | --- |
| `BITKURUS_API_URL` | BitKuruş node base URL | `https://bitkurush.org` |
| `BITKURUS_ACTIVITY_LIMIT` | tokens page size | `50` |
| `TRONGRID_API_URL` | TronGrid base URL | `https://api.trongrid.io` |
| `TRONGRID_API_KEY` | TronGrid key (rate-limit + broadcast) | _empty_ |
| `TRONGRID_LIMIT` | TRC20 query page size | `50` |
| `TRON_USDT_CONTRACT` | USDT TRC20 contract | mainnet USDT |
| `TRON_NODE_BIN` | Node binary for the signer | `node` |
| `TRON_FEE_LIMIT` | TRC20 gas limit (SUN) | `20000000` |
| `TRON_HOT_WALLET_ADDRESS` | central withdrawal wallet (base58) | _empty_ |
| `TRON_HOT_WALLET_KEY` | hot-wallet key, **`Crypt`-encrypted** | _empty_ |

A free TronGrid API key: <https://www.trongrid.io>.

## BK$ (BitKuruş) — Ed25519 + UTXO

- **Address = 64-char lowercase hex Ed25519 public key** (no prefix). Key pairs are generated with
  PHP `sodium` (`App\Support\BkKeypair`).
- **Deposits:** the address owns *tokens* (UTXOs). `App\Services\Chain\BkChainVerifier` reads
  `GET /api/wallet/{addr}` and credits each **active** token once (dedup by `token_id`). Incoming
  funds may appear with `origin: "split"` — detection is UTXO-based, not activity-kind-based.
- **Withdrawals:** `App\Services\Chain\BkWithdrawalService` selects UTXOs (greedy largest-first)
  and builds one of:
  - **transfer** (1→1) — a single UTXO exactly covers the amount;
  - **split** (1→2) — one UTXO > amount → recipient output + change back to the sender;
  - **merge** (N→1) — combine several UTXOs first (commit, refresh version), then transfer/split.
  Value is conserved (`Σinputs = Σoutputs`, no explicit fee).
- **Signing:** the transaction payload (all fields except `signature`) is serialized as
  **canonical JSON** (`App\Support\CanonicalJson`: keys sorted ascending, decimal-string amounts,
  no unicode/slash escaping) and signed with Ed25519 → 128-hex signature. Posted to
  `POST /api/tx/submit`; polled via `GET /api/tx/{tx_id}` until `committed`.
- **Change tokens** returned to the sender are recorded in `chain_internal_tokens` so the deposit
  scanner never mistakes them for new deposits.

Docs: <https://bitkurush.org/docs>

## USDT (Tron / TRC20) — secp256k1 via Node

PHP has no bundled secp256k1 and no `ext-gmp` requirement here, so signing is delegated to a small
Node helper (**`scripts/tron-signer.cjs`**, using `elliptic` + `js-sha3`), called from PHP via
`App\Support\TronCrypto`. The private key is passed over **stdin** (never argv).

- **Key pair / address:** secp256k1 → keccak256(pubkey)[-20:] → `0x41` prefix → base58check `T...`.
- **Deposits:** `App\Services\Chain\TronUsdtVerifier` queries TronGrid
  `/v1/accounts/{addr}/transactions/trc20?only_to=true&only_confirmed=true` for the USDT contract;
  credits confirmed incoming transfers (dedup by transaction id).
- **Withdrawals:** `App\Services\Chain\TronWithdrawalService` builds a TRC20 `transfer(address,
  uint256)` call via TronGrid `triggersmartcontract` (ABI-encoded params), signs the returned
  `txID` with secp256k1, then `broadcasttransaction`.

### Hot wallet & gas reality

Tron charges **energy/bandwidth paid in TRX** for every TRC20 transfer — this is unavoidable.
Therefore USDT withdrawals are sent from **one central hot wallet** (`TRON_HOT_WALLET_*`), not from
each user's deposit address. Fund the hot wallet with:
- enough **USDT** to cover withdrawals, and
- some **TRX** for gas (a fresh Tron account also needs ~1 TRX to activate).

User deposits accumulate at per-user addresses (key pairs the exchange controls) and can be swept
to the hot wallet operationally; the internal ledger tracks balances independently.

## Node dependency

```bash
npm install   # installs elliptic + js-sha3 used by scripts/tron-signer.cjs
```

Set `TRON_NODE_BIN` if `node` is not on the PATH of the PHP process (e.g. under systemd).

## Validating the setup

- BK$ signing was verified against the live node and by local signature recovery.
- USDT signing was verified against TronGrid (an unfunded wallet is rejected with
  `account does not exist`, **not** a signature error) and by recovering the signer address.
- Unit/feature tests cover the UTXO logic, ABI encoding, and reconciliation with fake HTTP.

---

## Türkçe özet

Deposit adresleri borsanın kontrol ettiği anahtar çiftleridir (özel anahtar `deposit_secret`'te
şifreli). **BK$** Ed25519 + UTXO (transfer/split/merge, canonical-JSON imza, `sodium`); **USDT**
Tron TRC20, secp256k1 imzalama PHP'de gmp olmadığı için Node yardımcısına (`scripts/tron-signer.cjs`)
delege edilir. Tron'da her transfer **TRX gas** ister; bu yüzden USDT çekimleri **fonlanmış merkezi
hot-wallet'tan** (`TRON_HOT_WALLET_*`, USDT + TRX ile) gönderilir. Yapılandırma `config/services.php`
+ `.env`. TronGrid API anahtarı gereklidir.
