<?php

namespace App\Http\Requests\ProductComments;

use App\Models\ProductCommentReport;
use Illuminate\Foundation\Http\FormRequest;

class ReportProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $comment = $this->route('comment');

            if (!$user || !$comment) return;

            $hasPending = ProductCommentReport::query()
                ->where('comment_id', $comment->id)
                ->where('reporter_id', $user->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                $validator->errors()->add(
                    'reason',
                    'У вас вже є активна скарга на цей коментар.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Поле причини скарги обов’язкове.',
            'reason.max' => 'Причина скарги не може перевищувати 5000 символів.',
        ];
    }
}