<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartPreviewRequest extends FormRequest
{
    public const MAX_QUANTITY = 99;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }
}
