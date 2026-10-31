<?php

namespace App\Http\Requests;

class CheckoutQuoteRequest extends CartPreviewRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'governorate_id' => ['nullable', 'integer'],
            'coupon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
