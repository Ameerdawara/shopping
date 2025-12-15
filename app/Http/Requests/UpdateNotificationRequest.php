<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     $notification = $this->route('notification'); // النموذج الممرّر في الروت
    //     $user = auth()->user();

    //     // المستخدم يمكنه تعديل إشعاره فقط، والأدمن يمكنه تعديل أي إشعار
    //     return $user && ($user->is_admin || $notification->user_id === $user->id);
    // }

    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'string', 'max:255'],
            'is_read' => ['sometimes', 'boolean'],
        ];
    }
}
