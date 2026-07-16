<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Çekirdek market ayarları zorunlu; bot/simülatör alanları opsiyonel (sometimes),
        // böylece yalnızca komisyon güncellemesi gibi kısmi güncellemeler de çalışır.
        return [
            'maker_fee' => ['required', 'numeric', 'min:0', 'max:0.1'],
            'taker_fee' => ['required', 'numeric', 'min:0', 'max:0.1'],
            'min_order_size' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'bot_active' => ['sometimes', 'boolean'],
            'bot_reference_price' => ['sometimes', 'numeric', 'min:0.000001'],
            'bot_volatility' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'bot_spread' => ['sometimes', 'numeric', 'min:0.01', 'max:10'],
            'bot_min_qty' => ['sometimes', 'numeric', 'min:0.1'],
            'bot_max_qty' => ['sometimes', 'numeric', 'min:0.1', 'gte:bot_min_qty'],
            'bot_interval' => ['sometimes', 'integer', 'min:1', 'max:3600'],
            'simulator_active' => ['sometimes', 'boolean'],
            'simulator_interval' => ['sometimes', 'integer', 'min:5', 'max:3600'],
        ];
    }
}
