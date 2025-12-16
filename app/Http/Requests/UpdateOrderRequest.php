<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateOrderRequest extends FormRequest
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
            'user_id' => ['sometimes', 'exists:users,id'],
            'payment_id' => ['sometimes', 'exists:payments,id'],
            'order_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('orders', 'order_number')->ignore($this->order),
            ],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:pending,paid,shipped,delivered,cancelled'],
            'shipping_address' => ['sometimes', 'string', 'max:255'],
            'shipping_city' => ['sometimes', 'string', 'max:255'],
            'shipping_phone' => ['sometimes', 'string', 'max:20'],
            'delivered_at' => ['nullable', 'date'],
        ];
    }
}
