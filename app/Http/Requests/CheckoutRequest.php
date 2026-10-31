<?php

namespace App\Http\Requests;

use App\Models\Governorate;
use App\Support\Phone;
use Illuminate\Validation\Rule;

class CheckoutRequest extends CartPreviewRequest
{
    /**
     * The cart arrives as JSON in a hidden field, because it lives in the browser.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->string('phone')->toString()),
            'items' => json_decode($this->string('cart')->toString(), true) ?? [],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'governorate_id' => ['required', 'integer', Rule::exists(Governorate::class, 'id')->where('is_active', true)],
            'area' => ['required', 'string', 'max:255'],
            'landmark' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon' => ['nullable', 'string', 'max:50'],
            // Hidden from people, so only bots fill it in.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('shop.checkout.name'),
            'phone' => __('shop.checkout.phone'),
            'governorate_id' => __('shop.checkout.governorate'),
            'area' => __('shop.checkout.area'),
            'landmark' => __('shop.checkout.landmark'),
            'notes' => __('shop.checkout.notes'),
            'coupon' => __('shop.checkout.coupon'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => __('shop.checkout.phone_invalid')];
    }
}
