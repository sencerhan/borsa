# Güvenlik Politikası / Security Policy

## 🇹🇷 Türkçe

### Denetlenmemiş yazılım uyarısı
Bu proje **bağımsız bir güvenlik denetiminden geçmemiştir** ve eğitim/referans amaçlıdır.
Custody, özel anahtar yönetimi ve fon güvenliği kritik konulardır. Gerçek fonlarla kullanım
tamamen kullanıcının sorumluluğundadır.

### Özel anahtar / sır yönetimi
- Gerçek sırlar (`APP_KEY`, `TRON_HOT_WALLET_KEY`, `TRONGRID_API_KEY`, `REVERB_APP_SECRET`)
  **asla** repoya veya `.env.example`'a yazılmaz. `.env` git tarafından takip edilmez.
- Zincir özel anahtarları veritabanında `Crypt::encryptString` ile (APP_KEY'e bağlı) şifreli
  tutulur; `Wallet` modelinde `deposit_secret` alanı serileştirmeden gizlenir (`$hidden`).
- Hot-wallet anahtarını ortam değişkeninde şifreli tutun; düz metin private key koymayın.

### Açık bildirimi
Bir güvenlik açığı bulursanız **herkese açık issue açmayın.** Bunun yerine repo sahibine
özel olarak (GitHub Security Advisory veya e-posta ile) bildirin. Düzeltme yayımlanana kadar
detayı gizli tutmanızı rica ederiz.

---

## 🇬🇧 English

### Unaudited software notice
This project has **not undergone an independent security audit** and is intended for
education/reference. Custody, private-key management, and fund safety are critical. Using it
with real funds is entirely at your own risk.

### Private-key / secret handling
- Real secrets (`APP_KEY`, `TRON_HOT_WALLET_KEY`, `TRONGRID_API_KEY`, `REVERB_APP_SECRET`) are
  **never** committed or placed in `.env.example`. `.env` is git-ignored.
- Chain private keys are stored encrypted via `Crypt::encryptString` (tied to APP_KEY); the
  `Wallet` model hides `deposit_secret` from serialization (`$hidden`).
- Keep the hot-wallet key encrypted in the environment; never store a plaintext private key.

### Reporting a vulnerability
If you find a vulnerability, **do not open a public issue.** Instead, report it privately to the
repository owner (via GitHub Security Advisory or email). Please keep details confidential until
a fix is released.
