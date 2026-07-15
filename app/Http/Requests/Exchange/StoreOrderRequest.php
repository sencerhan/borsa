<?php

namespace App\Http\Requests\Exchange;

use App\Enums\OrderSide;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isLimit = $this->input('type') === OrderType::Limit->value;
        $isMarketBuy = $this->input('type') === OrderType::Market->value
            && $this->input('side') === OrderSide::Buy->value;

        return [
            'market_id' => ['required', 'integer', Rule::exists('markets', 'id')],
            'side' => ['required', Rule::enum(OrderSide::class)],
            'type' => ['required', Rule::enum(OrderType::class)],
            // Limit emirde fiyat zorunlu
            'price' => [Rule::requiredIf($isLimit), 'nullable', 'numeric', 'gt:0'],
            // Market alış hariç base miktarı zorunlu
            'quantity' => [Rule::requiredIf(! $isMarketBuy), 'nullable', 'numeric', 'gt:0'],
            // Market alışta quote bütçesi zorunlu
            'quote_amount' => [Rule::requiredIf($isMarketBuy), 'nullable', 'numeric', 'gt:0'],
        ];
    }
}
