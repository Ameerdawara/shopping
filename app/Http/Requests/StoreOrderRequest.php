<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'user_id' => [ 'exists:users,id'],
            'total_price' => [ 'numeric', 'min:0','default:0'],
            'status' => [ 'in:pending,paid,shipped,delivered,cancelled'],
            'delivered_at' => ['nullable', 'date'],
        ];
    }
}
