<?php

return [
    'side' => [
        'buy' => 'Alış',
        'sell' => 'Satış',
    ],
    'type' => [
        'limit' => 'Limit',
        'market' => 'Market',
    ],
    'status' => [
        'open' => 'Açık',
        'partially_filled' => 'Kısmen Doldu',
        'filled' => 'Doldu',
        'cancelled' => 'İptal Edildi',
    ],
    'transfer_status' => [
        'pending' => 'Bekliyor',
        'completed' => 'Tamamlandı',
        'failed' => 'Başarısız',
    ],
    'errors' => [
        'insufficient_balance' => ':asset bakiyeniz yetersiz.',
        'below_minimum' => 'Emir miktarı minimumun (:minimum) altında.',
        'empty_book' => 'Karşı tarafta eşleşecek emir yok. Market emri verilemez.',
        'market_not_found' => ':symbol piyasası bulunamadı.',
        'order_not_found' => 'Emir bulunamadı.',
        'market_inactive' => 'Bu piyasa şu an işleme kapalı.',
        'order_not_cancelable' => 'Bu emir iptal edilemez.',
        'invalid_price_quantity' => 'Fiyat ve miktar sıfırdan büyük olmalıdır.',
        'invalid_quote_amount' => 'İşlem tutarı sıfırdan büyük olmalıdır.',
    ],
    'flash' => [
        'order_placed' => 'Emriniz alındı.',
        'order_cancelled' => 'Emir iptal edildi.',
        'deposit_requested' => 'Para yatırma talebiniz işleniyor.',
        'withdrawal_requested' => 'Para çekme talebiniz işleniyor.',
        'market_updated' => 'Market ayarları güncellendi.',
    ],
];
