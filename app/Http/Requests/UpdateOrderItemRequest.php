<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id'   => ['sometimes', 'exists:orders,id'],
            'product_id' => ['sometimes', 'exists:products,id'],
            'price'      => ['sometimes', 'numeric', 'min:0'],
            'quantity'   => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
