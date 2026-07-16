<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Borsanın kendi ürettiği zincir token'ı (çekim para üstü / merge çıktısı).
 * Deposit tarayıcısı bu token'ları yatırma olarak kredilemez.
 *
 * @property string $asset
 * @property string $token_id
 */
class ChainInternalToken extends Model
{
    protected $fillable = ['asset', 'token_id'];

    /** Verilen token_id borsanın kendi ürettiği bir token mı? */
    public static function isInternal(string $asset, string $tokenId): bool
    {
        return static::where('asset', $asset)->where('token_id', $tokenId)->exists();
    }

    /** Bir token_id'yi dahili (kredilenmeyecek) olarak işaretler. */
    public static function mark(string $asset, string $tokenId): void
    {
        static::firstOrCreate(['asset' => $asset, 'token_id' => $tokenId]);
    }
}
