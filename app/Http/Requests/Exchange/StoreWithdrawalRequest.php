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
        return [
            'asset' => ['required', 'string', Rule::exists('assets', 'symbol')],
            'amount' => ['required', 'numeric', 'gt:0'],
            // BK$ gerçek zincir çekimi olduğu için alıcı adresi zorunlu ve 64-hex olmalı.
            'address' => [
                Rule::requiredIf(fn () => $this->input('asset') === 'BK$'),
                'nullable',
                'string',
                'max:255',
                Rule::when(
                    $this->input('asset') === 'BK$',
                    ['regex:/^[0-9a-f]{64}$/'],
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.regex' => 'BK$ adresi 64 karakter küçük harf hex olmalıdır.',
        ];
    }
}
