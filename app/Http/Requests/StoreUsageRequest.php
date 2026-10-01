<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key') ?: $this->input('idempotency_key');

        if ($key) {
            $this->merge(['idempotency_key' => $key]);
        }
    }

    public function rules(): array
    {
        $merchant = $this->attributes->get('merchant');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('merchant_id', $merchant?->id)),
            ],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'usage_date' => ['required', 'date', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
