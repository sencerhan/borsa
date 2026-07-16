<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $asset
 * @property string $amount
 * @property string|null $address
 * @property WithdrawalStatus $status
 * @property string|null $reference
 * @property CarbonImmutable|null $processed_at
 */
class Withdrawal extends Model
{
    protected $fillable = ['user_id', 'asset', 'amount', 'address', 'status', 'reference', 'txid', 'processed_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
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
