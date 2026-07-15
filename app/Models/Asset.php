<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $symbol
 * @property string $name
 * @property string|null $network
 * @property int $scale
 */
class Asset extends Model
{
    protected $fillable = ['symbol', 'name', 'network', 'scale'];

    protected function casts(): array
    {
        return [
            'scale' => 'integer',
        ];
    }
}
