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
     * @param  array{
     *     maker_fee?: string, taker_fee?: string, min_order_size?: string, is_active?: bool,
     *     bot_active?: bool, bot_reference_price?: string, bot_volatility?: string, bot_spread?: string,
     *     bot_min_qty?: string, bot_max_qty?: string, bot_interval?: int,
     *     simulator_active?: bool, simulator_interval?: int
     * }  $data
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
            $market->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('bot_active', $data)) {
            $market->bot_active = (bool) $data['bot_active'];
        }

        if (isset($data['bot_reference_price'])) {
            $market->bot_reference_price = $data['bot_reference_price'];
        }

        if (isset($data['bot_volatility'])) {
            // Yüzde girdisini decimal değere çevirerek kaydet (%0.5 -> 0.005)
            $market->bot_volatility = BigMath::div($data['bot_volatility'], '100');
        }

        if (isset($data['bot_spread'])) {
            // Yüzde girdisini decimal değere çevirerek kaydet (%1.0 -> 0.01)
            $market->bot_spread = BigMath::div($data['bot_spread'], '100');
        }

        if (isset($data['bot_min_qty'])) {
            $market->bot_min_qty = $data['bot_min_qty'];
        }

        if (isset($data['bot_max_qty'])) {
            $market->bot_max_qty = $data['bot_max_qty'];
        }

        if (isset($data['bot_interval'])) {
            $market->bot_interval = (int) $data['bot_interval'];
        }

        if (array_key_exists('simulator_active', $data)) {
            $market->simulator_active = (bool) $data['simulator_active'];
        }

        if (isset($data['simulator_interval'])) {
            $market->simulator_interval = (int) $data['simulator_interval'];
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
