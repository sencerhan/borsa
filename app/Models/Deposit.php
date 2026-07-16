<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $asset
 * @property string $amount
 * @property DepositStatus $status
 * @property string|null $reference
 * @property CarbonImmutable|null $processed_at
 */
class Deposit extends Model
{
    protected $fillable = ['user_id', 'asset', 'amount', 'status', 'reference', 'txid', 'processed_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => DepositStatus::class,
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
