<?php

namespace App\Support;

/**
 * BitKuruş imza payload'ı için canonical (kanonik) JSON serileştirme.
 *
 * Zincirle byte-birebir aynı olmalı:
 *  - Nesne anahtarları artan sırada (UTF-8 byte sırası)
 *  - Diziler gönderim sırasını korur
 *  - `\uXXXX` escape yok (ham UTF-8), `/` escape edilmez
 *  - Para tutarları ondalık string (JSON sayısı değil) — bunu çağıran sağlar
 *
 * @see https://bitkurush.org/docs
 */
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /** Nesne anahtarlarını özyinelemeli olarak artan sırada sıralar; dizi sırasını korur. */
    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        // Liste (indeksli dizi): sıra korunur, elemanlar normalize edilir.
        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        // Asosyatif (nesne): anahtarları byte sırasına göre sırala.
        ksort($value, SORT_STRING);

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item);
        }

        return $normalized;
    }
}
