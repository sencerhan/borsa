<?php

namespace App\Services\Admin;

use App\Models\Market;
use App\Support\BigMath;

/**
 * Admin: market ayarları (komisyon, minimum emir, aktiflik) yönetimi.
 */
class MarketSettingService
{
    /**
     * @param  array{maker_fee?: string, taker_fee?: string, min_order_size?: string, is_active?: bool}  $data
     */
    public function update(Market $market, array $data): Market
    {
        if (isset($data['maker_fee'])) {
            $this->assertFeeRange($data['maker_fee']);
            $market->maker_fee = $data['maker_fee'];
        }

        if (isset($data['taker_fee'])) {
            $this->assertFeeRange($data['taker_fee']);
            $market->taker_fee = $data['taker_fee'];
        }

        if (isset($data['min_order_size'])) {
            $market->min_order_size = $data['min_order_size'];
        }

        if (array_key_exists('is_active', $data)) {
            $market->is_active = $data['is_active'];
        }

        $market->save();

        return $market;
    }

    /** Komisyon oranı 0 ile 0.1 (%10) arasında olmalı. */
    private function assertFeeRange(string $fee): void
    {
        if (BigMath::lt($fee, '0') || BigMath::gt($fee, '0.1')) {
            throw new \InvalidArgumentException('Komisyon oranı 0 ile 0.1 arasında olmalıdır.');
        }
    }
}
