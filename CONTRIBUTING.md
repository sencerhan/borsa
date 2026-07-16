# Katkı Rehberi / Contributing

> TR + EN. Teşekkürler! / Thanks for contributing!

## 🇹🇷 Türkçe

### Geliştirme kurulumu
```bash
composer setup      # bağımlılıklar + .env + key + migrate + npm build
php artisan dev     # Vite + Reverb + otonom daemon
```

### Mimari sözleşme (ASLA ihlal edilmez)
Bu proje katı bir katman ayrımı uygular (bkz. [CLAUDE.md](CLAUDE.md)):

- **İş mantığı yalnızca `app/Services/` içindedir.** Controller/Model/Route/Job ince kalır.
- **Para/bakiye işlemleri** her zaman `DB::transaction` + `lockForUpdate` ile yapılır ve
  `ledger_entries`'e yazılır.
- Domain hataları için **custom exception** sınıfları kullanılır (`app/Exceptions/`).
- Validasyon **Form Request**'lerde, sabitler **Enum**'larda.
- Kullanıcıya görünen tüm metinler **tr + en** i18n'den geçer (hardcoded string yok).
- **Her yeni iş mantığı için test** yazılır.

### Kalite kapısı (PR'dan önce)
```bash
composer ci:check   # Pint + Prettier + ESLint + PHPStan + testler
```
Ayrı ayrı: `composer test`, `composer types:check`, `npm run lint`, `npm run types:check`.

### PR süreci
1. Bir dal aç (`feature/...` veya `fix/...`).
2. Değişikliğini yap + test ekle.
3. `composer ci:check` yeşil olsun.
4. Açık bir başlık ve açıklama ile PR gönder. İlgili issue'yu bağla.

---

## 🇬🇧 English

### Dev setup
```bash
composer setup      # deps + .env + key + migrate + npm build
php artisan dev     # Vite + Reverb + autonomous daemon
```

### Architecture contract (never violated)
This project enforces strict layering (see [CLAUDE.md](CLAUDE.md)):

- **Business logic lives only in `app/Services/`.** Controllers/Models/Routes/Jobs stay thin.
- **Money/balance operations** always use `DB::transaction` + `lockForUpdate` and write to
  `ledger_entries`.
- Domain errors use **custom exceptions** (`app/Exceptions/`).
- Validation in **Form Requests**, constants in **Enums**.
- All user-facing text goes through **tr + en** i18n (no hardcoded strings).
- **Write tests for every new piece of business logic.**

### Quality gate (before a PR)
```bash
composer ci:check   # Pint + Prettier + ESLint + PHPStan + tests
```

### PR process
1. Branch off (`feature/...` or `fix/...`).
2. Make the change + add tests.
3. Ensure `composer ci:check` is green.
4. Open a PR with a clear title/description; link the related issue.
