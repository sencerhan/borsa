<?php

namespace App\Http\Requests\Exchange;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithdrawalRequest extends FormRequest
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
        $asset = $this->input('asset');

        return [
            'asset' => ['required', 'string', Rule::exists('assets', 'symbol')],
            'amount' => ['required', 'numeric', 'gt:0'],
            // BK$ ve USDT gerçek zincir çekimi olduğu için alıcı adresi zorunlu ve ağa uygun olmalı.
            'address' => [
                Rule::requiredIf(fn () => in_array($asset, ['BK$', 'USDT'], true)),
                'nullable',
                'string',
                'max:255',
                // BK$: 64-hex Ed25519. USDT: base58 Tron adresi (T...).
                Rule::when($asset === 'BK$', ['regex:/^[0-9a-f]{64}$/']),
                Rule::when($asset === 'USDT', ['regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.regex' => $this->input('asset') === 'USDT'
                ? 'Geçerli bir TRON (T...) adresi girin.'
                : 'BK$ adresi 64 karakter küçük harf hex olmalıdır.',
        ];
    }
}
