# Deployment / Dağıtım

> EN + kısa TR notları.

## Local development

```bash
composer setup
php artisan dev     # runs Vite + Reverb + the autonomous daemon together
```

`php artisan dev` is enough for local work — deposits are also scanned on the wallet page load, so
you don't strictly need the daemon while developing.

## Production

Two long-running processes are needed besides PHP-FPM/nginx:

1. **Reverb** — the WebSocket server for live updates.
2. **`borsa:daemon`** — deposit scanning, withdrawal reconciliation, market-maker bot, queue, and
   stats broadcasting in one loop.

Run them as **systemd services** (templates in [`deploy/`](../deploy/)). Adjust `User`, `Group`,
`WorkingDirectory`, and the PHP path, then:

```bash
sudo cp deploy/bitkurus-reverb.service  /etc/systemd/system/
sudo cp deploy/bitkurus-daemon.service  /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now bitkurus-reverb bitkurus-daemon

# Status / logs
systemctl status bitkurus-daemon
journalctl -u bitkurus-daemon -f
```

The daemon runs `queue:work` internally, so **do not** also run a separate queue worker (it would
double-process). Only one Reverb instance may bind the port.

### What the daemon covers
- `deposits:scan` — credit confirmed on-chain deposits (BK$ + USDT)
- `withdrawals:reconcile` — resolve stuck withdrawals by chain status
- market-maker bot + transaction simulator (admin-toggled per market)
- queue draining + stats broadcast

You can also run these one-off: `php artisan deposits:scan`, `php artisan withdrawals:reconcile`.

## nginx notes

**FastCGI buffers.** Laravel Vite emits a large preload `Link` response header; nginx's default
FastCGI buffer can overflow it, causing `502 upstream sent too big header`. In the PHP location:

```nginx
location ~ \.php$ {
    # ... fastcgi_pass etc ...
    fastcgi_buffer_size 32k;
    fastcgi_buffers 16 16k;
    fastcgi_busy_buffers_size 32k;
}
```

**`public/hot`.** This file is created by `npm run dev` (Vite dev server). If it remains after you
switch to a production build, `@vite` will try to load assets from the dev server and you'll get a
blank page. Remove it: `rm public/hot`.

## Environment & chain

- Build assets for production: `npm run build` (then `php artisan optimize`).
- Set `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` per your infra.
- Configure the chain and hot wallet per [CHAIN_INTEGRATION.md](CHAIN_INTEGRATION.md). Fund the
  USDT hot wallet with USDT + TRX. Set `TRON_NODE_BIN` to the absolute `node` path if it isn't on
  the service's PATH.

---

## Türkçe özet

Yerelde `php artisan dev` yeterli (Vite + Reverb + daemon birlikte). Prod'da **Reverb** ve
**`borsa:daemon`** systemd servisi olarak çalıştırılır (şablonlar `deploy/`). Daemon kuyruğu kendi
içinde işler — ayrı `queue:work` çalıştırma. nginx'te Vite preload `Link` header'ı için
`fastcgi_buffer_size 32k; fastcgi_buffers 16 16k;` ekle (502 düzeltmesi) ve build'e geçince
`public/hot`'u sil. Zincir + hot-wallet yapılandırması için CHAIN_INTEGRATION.md.
