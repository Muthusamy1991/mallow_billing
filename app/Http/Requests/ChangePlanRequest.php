<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $merchant = $this->attributes->get('merchant');

        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where(fn ($q) => $q->where('merchant_id', $merchant?->id)->where('is_active', true)),
            ],
            'effective_date' => ['required', 'date'],
        ];
    }
}
