<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveProductCommentReportRequest extends FormRequest
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
            'status' => ['required', Rule::in(['resolved', 'rejected'])],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Статус обробки обов’язковий.',
            'status.in' => 'Дозволені статуси: resolved або rejected.',
            'resolution_note.max' => 'Коментар модератора не може перевищувати 5000 символів.',
        ];
    }
}