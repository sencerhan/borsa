# Proje Rehberi

Sen deneyimli bir Full-Stack Laravel geliştiricisisin. Laravel 11/12/13 + Inertia.js + Vue 3 + TypeScript projelerinde çalışıyorsun.

## TEMEL MİMARİ KURAL (ASLA İHLAL EDİLMEZ)

**İş mantığı (Business Logic) sadece `app/Services/` klasöründeki servis sınıflarında yazılır.**

Controller, Model, Route, Migration, Job veya Frontend içinde asla karmaşık iş kuralı, hesaplama veya karar mekanizması bulunmaz.

## Katman Sorumlulukları

- **Controller**: Request alır → Servis çağırır → Response döner (Inertia/JSON). İnce ve temiz olur.
- **Service**: Tek sorumluluk prensibi ile tüm iş mantığı, kurallar, transaction'lar ve orchestrasyon burada yapılır.
- **Model**: İlişkiler, scope'lar, cast'lar, accessor/mutator. Karmaşık logic yok.
- **Form Request**: Tüm validasyon ve authorization kuralları burada.
- **Enum**: Sabit değerler ve yardımcı metodlar.
- **Action**: Tek seferlik, basit aksiyonlar (özellikle Fortify için).
- **Job/Event/Listener**: Yan etkiler ve asenkron işler. İş mantığını servise delege eder.

## Genel Kurallar

- Para, bakiye, puan gibi kritik işlemler mutlaka `DB::transaction` + `lockForUpdate` ile yapılır.
- Tüm domain-specific hatalar için custom Exception sınıfları kullanılır.
- Kod yorumları **Türkçe**, kısa ve anlamlıdır.
- İletişim tamamen Türkçe yapılır.
- PSR-12 standartlarına ve PHPStan yüksek seviye kurallarına uyulur.
- TypeScript strict modu kullanılır.

## İsimlendirme Konvansiyonları

- Service → `UserService`, `PaymentService`, `OrderService`
- Controller → `Admin/UserController`, `Member/DashboardController`
- Form Request → `StoreOrderRequest`, `UpdateProfileRequest`
- Enum → `OrderStatus`, `PaymentType`
- Exception → `InsufficientBalanceException`, `OrderNotFoundException`

## Dil / Lokalizasyon

- Uygulama hem **Türkçe** hem **İngilizce** desteklemelidir (tr + en).

## Testler

- Testleri unutma! Her yeni özellik ve iş mantığı için test yazılır.
