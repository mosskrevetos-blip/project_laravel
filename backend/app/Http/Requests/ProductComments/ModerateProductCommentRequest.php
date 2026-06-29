<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;
    }

    public function rules(): array
    {
        return [
            'moderation_status' => ['required', Rule::in(['approved', 'rejected'])],
            'moderation_reject_reason' => [
                'nullable',
                'string',
                'max:5000',
                'required_if:moderation_status,rejected',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'moderation_status.required' => 'Статус модерації обов’язковий.',
            'moderation_status.in' => 'Дозволені статуси: approved або rejected.',
            'moderation_reject_reason.required_if' => 'Вкажіть причину відхилення.',
            'moderation_reject_reason.max' => 'Причина відхилення не може перевищувати 5000 символів.',
        ];
    }
}