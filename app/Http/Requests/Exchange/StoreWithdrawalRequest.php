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
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
