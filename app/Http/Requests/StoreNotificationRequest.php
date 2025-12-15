<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // فقط الأدمن يمكنه إنشاء إشعارات
        return auth()->user() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'content' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required',
            'user_id.exists' => 'User must exist',
            'content.required' => 'Content is required',
            'content.string' => 'Content must be a string',
            'content.max' => 'Content can be maximum 255 characters',
        ];
    }
}
