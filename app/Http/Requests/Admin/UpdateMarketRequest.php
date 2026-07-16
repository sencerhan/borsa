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
        return [
            'maker_fee' => ['required', 'numeric', 'min:0', 'max:0.1'],
            'taker_fee' => ['required', 'numeric', 'min:0', 'max:0.1'],
            'min_order_size' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'bot_active' => ['required', 'boolean'],
            'bot_reference_price' => ['required', 'numeric', 'min:0.000001'],
            'bot_volatility' => ['required', 'numeric', 'min:0', 'max:5'],
            'bot_spread' => ['required', 'numeric', 'min:0.01', 'max:10'],
            'bot_min_qty' => ['required', 'numeric', 'min:0.1'],
            'bot_max_qty' => ['required', 'numeric', 'min:0.1', 'gte:bot_min_qty'],
            'bot_interval' => ['required', 'integer', 'min:1', 'max:3600'],
            'simulator_active' => ['required', 'boolean'],
            'simulator_interval' => ['required', 'integer', 'min:5', 'max:3600'],
        ];
    }
}
