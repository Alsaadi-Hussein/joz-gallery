<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DropsArrayFields;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    use DropsArrayFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->dropArrayFields();

        $this->merge([
            'number' => mb_strtoupper(trim($this->string('number')->toString())),
            'phone' => Phone::normalize($this->string('phone')->toString()),
        ]);
    }

    /**
     * Both are required: an order number alone must never reveal an order.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'number' => __('shop.track.number'),
            'phone' => __('shop.checkout.phone'),
        ];
    }
}
