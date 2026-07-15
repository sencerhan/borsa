<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $asset
 * @property LedgerEntryType $type
 * @property string $amount
 * @property string $available_after
 * @property string $locked_after
 */
class LedgerEntry extends Model
{
    protected $fillable = [
        'user_id', 'asset', 'type', 'amount', 'available_after', 'locked_after',
        'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'decimal:18',
            'available_after' => 'decimal:18',
            'locked_after' => 'decimal:18',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
