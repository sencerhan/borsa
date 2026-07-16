<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $asset
 * @property string|null $deposit_address
 * @property string|null $deposit_secret
 * @property string $available
 * @property string $locked
 */
class Wallet extends Model
{
    protected $fillable = ['user_id', 'asset', 'deposit_address', 'deposit_secret', 'available', 'locked'];

    // Özel anahtar asla API/JSON yanıtına sızmamalı.
    protected $hidden = ['deposit_secret'];

    protected function casts(): array
    {
        return [
            'available' => 'decimal:18',
            'locked' => 'decimal:18',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
